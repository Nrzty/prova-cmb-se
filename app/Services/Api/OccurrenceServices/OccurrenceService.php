<?php

namespace App\Services\Api\OccurrenceServices;

use App\DTOs\OccurrenceFilterDTO;
use App\Enums\EventInboxEnums\EventInboxSource;
use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Enums\EventInboxEnums\EventInboxType;
use App\Enums\OccurrenceIntegrationStatus;
use App\Exceptions\IdempotencyConflictException;
use App\Jobs\OccurrenceJobs\ProcessOccurrenceResolveJob;
use App\Jobs\OccurrenceJobs\ProcessOccurrenceStartJob;
use App\Models\EventInbox;
use App\Models\Occurrence;
use App\Services\Api\OccurrenceServices\IntegrationServices\IntegrationResult;
use App\Support\Database\DatabaseErrorHelper;
use Illuminate\Database\QueryException;
use LogicException;

class OccurrenceService
{
    private const LIMIT_PER_PAGE = 100;

    public function __construct(

    ) { }

    public function list(OccurrenceFilterDTO $filters, int $amountPerPage)
    {
        $perPage = min($amountPerPage, self::LIMIT_PER_PAGE);

        $query = Occurrence::query();

        if ($filters->getStatus())
        {
            $query->where("status", $filters->getStatus()->value);
        }

        if ($filters->getType())
        {
            $query->where("type", $filters->getType()->value);
        }

        return $query->paginate($perPage);
    }

    public function startOccurrence(string $occurrenceId, string $idempotencyKey, array $meta = [])
    {
        return $this->registerLifecycleCommand($occurrenceId, $idempotencyKey, EventInboxType::STARTED, $meta);
    }

    public function resolveOccurrence(string $occurrenceId, string $idempotencyKey, array $meta = [])
    {
        return $this->registerLifecycleCommand($occurrenceId, $idempotencyKey, EventInboxType::RESOLVED, $meta);
    }

    private function registerLifecycleCommand(string $occurrenceId, string $idempotencyKey, EventInboxType $eventInboxType, array $meta = []): IntegrationResult
    {
        Occurrence::findOrFail($occurrenceId);

        $payload = [
            'occurrenceId' => $occurrenceId,
        ];

        $sourceValue = EventInboxSource::WEB_OPERATOR->value;

        try {
            $eventInbox = EventInbox::create([
                'idempotency_key' => $idempotencyKey,
                'source' => $sourceValue,
                'type' => $eventInboxType->value,
                'payload' => $payload,
                'status' => EventInboxStatus::PENDING->value,
            ]);

            switch ($eventInboxType)
            {
                case EventInboxType::STARTED:
                    ProcessOccurrenceStartJob::dispatch($eventInbox->id);
                    break;
                case EventInboxType::RESOLVED:
                    ProcessOccurrenceResolveJob::dispatch($eventInbox->id);
                    break;
                default:
                    throw new LogicException('EventInboxType não suportado para lifecycle.');
            }

            return new IntegrationResult($eventInbox->id, OccurrenceIntegrationStatus::CREATED);

        } catch (QueryException $exception) {
            if (! DatabaseErrorHelper::isUniqueViolation($exception))
            {
                throw $exception;
            }

            $verifyExistingEventInbox = EventInbox::where('idempotency_key', $idempotencyKey)
                ->where('type', $eventInboxType->value)
                ->where('source', $sourceValue)
                ->first();

            if (is_null($verifyExistingEventInbox))
            {
                throw $exception;
            }

            if ($verifyExistingEventInbox->payload === $payload)
            {
                return new IntegrationResult($verifyExistingEventInbox->id, OccurrenceIntegrationStatus::DUPLICATED);
            }

            throw new IdempotencyConflictException('O payload enviado diverge do registro existente.');
        }
    }
}
