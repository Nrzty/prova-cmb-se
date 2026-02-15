<?php

namespace App\Services\Api\OccurrenceServices\IntegrationServices;

use App\DTOs\OccurrenceDTO;
use App\Enums\EventInboxEnums\EventInboxSource;
use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Enums\EventInboxEnums\EventInboxType;
use App\Enums\OccurrenceIntegrationStatus;
use App\Exceptions\IdempotencyConflictException;
use App\Jobs\OccurrenceJobs\ProcessOccurrenceCreatedJob;
use App\Models\EventInbox;
use App\Support\Database\DatabaseErrorHelper;
use Illuminate\Database\QueryException;

class RegisterOccurrenceCommandService
{
    public function receiveExternalOccurrence(OccurrenceDTO $occurrenceDTO, string $idempotencyKey): IntegrationResult
    {
        $payload = $occurrenceDTO->toArray();

        $source = EventInboxSource::EXTERNAL_SYSTEM;
        $type = EventInboxType::CREATED;
        $status = EventInboxStatus::PENDING;

        try {
            $eventInbox = EventInbox::create([
                'idempotency_key' => $idempotencyKey,
                'source' => $source->value,
                'type' => $type->value,
                'payload' => $payload,
                'status' => $status->value,
            ]);

            ProcessOccurrenceCreatedJob::dispatch($eventInbox->id);

            return new IntegrationResult($eventInbox->id, OccurrenceIntegrationStatus::CREATED);

        } catch (QueryException $exception) {
            if (! DatabaseErrorHelper::isUniqueViolation($exception))
            {
                throw $exception;
            }

            $verifyExistingEventInbox = EventInbox::where('idempotency_key', $idempotencyKey)
                ->where('type', $type->value)
                ->where('source', $source->value)
                ->first();

            if (is_null($verifyExistingEventInbox))
            {
                throw $exception;
            }

            if ($verifyExistingEventInbox->payload === $payload)
            {
                return new IntegrationResult($verifyExistingEventInbox->id, OccurrenceIntegrationStatus::DUPLICATED);
            }

            throw new IdempotencyConflictException("O payload enviado diverge do registro existente.");
        }
    }
}
