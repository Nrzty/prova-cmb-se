<?php

namespace App\Services\Api\Dispatch;

use App\Enums\EventInboxEnums\EventInboxSource;
use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Enums\EventInboxEnums\EventInboxType;
use App\Enums\OccurrenceIntegrationStatus;
use App\Exceptions\IdempotencyConflictException;
use App\Jobs\DispatchJobs\ProcessDispatchClosedJob;
use App\Jobs\DispatchJobs\ProcessDispatchCreatedJob;
use App\Models\Dispatche;
use App\Models\EventInbox;
use App\Models\Occurrence;
use App\Services\Api\OccurrenceServices\IntegrationServices\IntegrationResult;
use App\Support\Database\DatabaseErrorHelper;
use Illuminate\Database\QueryException;

class DispatchService
{
    public function __construct(

    ) { }

    public function createDispatch(string $occurrenceId, string $resourceCode, string $idempotencyKey): IntegrationResult
    {
        Occurrence::findOrFail($occurrenceId);

        $payload = [
            'occurrenceId' => $occurrenceId,
            'resourceCode' => $resourceCode,
        ];

        $sourceValue = EventInboxSource::WEB_OPERATOR->value;
        $typeValue = EventInboxType::DISPATCH_CREATED->value;

        try {
            $eventInbox = EventInbox::create([
                'idempotency_key' => $idempotencyKey,
                'source' => $sourceValue,
                'type' => $typeValue,
                'payload' => $payload,
                'status' => EventInboxStatus::PENDING->value,
            ]);

            ProcessDispatchCreatedJob::dispatch($eventInbox->id);

            return new IntegrationResult($eventInbox->id, OccurrenceIntegrationStatus::CREATED);

        } catch (QueryException $exception) {
            if (! DatabaseErrorHelper::isUniqueViolation($exception))
            {
                throw $exception;
            }

            $existing = EventInbox::where('idempotency_key', $idempotencyKey)
                ->where('type', $typeValue)
                ->where('source', $sourceValue)
                ->first();

            if (! $existing)
            {
                throw $exception;
            }

            if ($existing->payload === $payload)
            {
                return new IntegrationResult($existing->id, OccurrenceIntegrationStatus::DUPLICATED);
            }

            throw new IdempotencyConflictException('O payload enviado diverge do registro existente.');
        }
    }

    public function closeDispatch(string $dispatchId, string $idempotencyKey): IntegrationResult
    {
        $dispatch = Dispatche::findOrFail($dispatchId);

        $payload = [
            'dispatchId' => $dispatchId,
            'occurrenceId' => $dispatch->occurrence_id,
        ];

        $sourceValue = EventInboxSource::WEB_OPERATOR->value;
        $typeValue = EventInboxType::DISPATCH_CLOSED->value;

        try {
            $eventInbox = EventInbox::create([
                'idempotency_key' => $idempotencyKey,
                'source' => $sourceValue,
                'type' => $typeValue,
                'payload' => $payload,
                'status' => EventInboxStatus::PENDING->value,
            ]);

            ProcessDispatchClosedJob::dispatch($eventInbox->id);

            return new IntegrationResult($eventInbox->id, OccurrenceIntegrationStatus::CREATED);

        } catch (QueryException $exception) {
            if (! DatabaseErrorHelper::isUniqueViolation($exception))
            {
                throw $exception;
            }

            $existing = EventInbox::where('idempotency_key', $idempotencyKey)
                ->where('type', $typeValue)
                ->where('source', $sourceValue)
                ->first();

            if (! $existing)
            {
                throw $exception;
            }

            if ($existing->payload === $payload)
            {
                return new IntegrationResult($existing->id, OccurrenceIntegrationStatus::DUPLICATED);
            }

            throw new IdempotencyConflictException('O payload enviado diverge do registro existente.');
        }
    }
}
