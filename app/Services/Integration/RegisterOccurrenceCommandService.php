<?php

namespace App\Services\Integration;
use App\DTOs\OccurrenceDTO;
use App\Enums\EventEnums\EventInboxSource;
use App\Enums\EventEnums\EventInboxStatus;
use App\Enums\EventEnums\EventInboxType;
use App\Enums\OccurrenceIntegrationStatus;
use App\Enums\SqlEnums\SqlUniqueViolation;
use App\Exceptions\IdempotencyConflictException;
use App\Models\EventInbox;
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
            return new IntegrationResult($eventInbox->id, OccurrenceIntegrationStatus::CREATED);

        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception))
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

    private function isUniqueViolation(QueryException $exception): bool
    {
        $errorCode = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($errorCode, array_column(SqlUniqueViolation::cases(), 'value'));
    }
}
