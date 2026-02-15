<?php

namespace App\Services\Api\Dispatch;

use App\Enums\DispatchEnums\DispatchStatus;
use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Enums\EventInboxEnums\EventInboxType;
use App\Enums\OccurrenceEnums\OccurrenceStatus;
use App\Models\AuditLog;
use App\Models\Dispatche;
use App\Models\EventInbox;
use App\Models\Occurrence;
use App\Support\Database\DatabaseErrorHelper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProcessDispatchCreatedService
{
    public function process(string $eventInboxId): void
    {
        $wasCreated = false;

        DB::transaction(function () use ($eventInboxId, &$wasCreated) {
            $event = EventInbox::where('id', $eventInboxId)
                ->lockForUpdate()
                ->first();

            if (! $event)
            {
                return;
            }

            if ($event->status !== EventInboxStatus::PENDING->value)
            {
                return;
            }

            if ($event->type !== EventInboxType::DISPATCH_CREATED->value)
            {
                throw new LogicException('EventInboxType inválido para ProcessDispatchCreatedService.');
            }

            $payload = $event->payload;
            $occurrenceId = $payload['occurrenceId'] ?? null;
            $resourceCode = $payload['resourceCode'] ?? null;

            if (! $occurrenceId || ! $resourceCode)
            {
                throw new LogicException('Payload inválido: occurrenceId/resourceCode ausentes.');
            }

            $occurrence = Occurrence::where('id', $occurrenceId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($occurrence->status, [OccurrenceStatus::RESOLVED->value, OccurrenceStatus::CANCELLED->value], true))
            {
                throw new LogicException('Não é permitido criar despacho para ocorrência resolved/cancelled.');
            }

            try {
                $dispatch = Dispatche::create([
                    'occurrence_id' => $occurrenceId,
                    'resource_code' => $resourceCode,
                    'status' => DispatchStatus::ASSIGNED->value,
                ]);

                $wasCreated = true;

            } catch (QueryException $exception) {
                if (! DatabaseErrorHelper::isUniqueViolation($exception))
                {
                    throw $exception;
                }

                $dispatch = Dispatche::where('occurrence_id', $occurrenceId)
                    ->where('resource_code', $resourceCode)
                    ->first();

                if (! $dispatch)
                {
                    throw $exception;
                }
            }

            if ($wasCreated)
            {
                AuditLog::create([
                    'entity_type' => Dispatche::class,
                    'entity_id' => $dispatch->id,
                    'action' => $event->type,
                    'before' => null,
                    'after' => $dispatch->toArray(),
                    'meta' => [
                        'source' => $event->source,
                        'event_type' => $event->type,
                        'event_id' => $event->id,
                        'occurrence_id' => $occurrenceId,
                    ],
                ]);
            }

            $event->update([
                'status' => EventInboxStatus::PROCESSED->value,
                'processed_at' => now(),
            ]);
        });
    }
}
