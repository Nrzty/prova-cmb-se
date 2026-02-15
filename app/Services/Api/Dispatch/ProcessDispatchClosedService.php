<?php

namespace App\Services\Api\Dispatch;

use App\Enums\DispatchEnums\DispatchStatus;
use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Enums\EventInboxEnums\EventInboxType;
use App\Models\AuditLog;
use App\Models\Dispatche;
use App\Models\EventInbox;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProcessDispatchClosedService
{
    public function process(string $eventInboxId): void
    {
        DB::transaction(function () use ($eventInboxId) {
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

            if ($event->type !== EventInboxType::DISPATCH_CLOSED->value)
            {
                throw new LogicException('EventInboxType inválido para ProcessDispatchClosedService.');
            }

            $payload = $event->payload;
            $dispatchId = $payload['dispatchId'] ?? null;

            if (! $dispatchId)
            {
                throw new LogicException('Payload inválido: dispatchId ausente.');
            }

            $dispatch = Dispatche::where('id', $dispatchId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($dispatch->status === DispatchStatus::CLOSED->value)
            {
                // idempotente lógicamente: já fechado
                $event->update([
                    'status' => EventInboxStatus::PROCESSED->value,
                    'processed_at' => now(),
                ]);

                return;
            }

            $before = $dispatch->toArray();

            $dispatch->update([
                'status' => DispatchStatus::CLOSED->value,
            ]);

            AuditLog::create([
                'entity_type' => Dispatche::class,
                'entity_id' => $dispatch->id,
                'action' => $event->type,
                'before' => $before,
                'after' => $dispatch->fresh()->toArray(),
                'meta' => [
                    'source' => $event->source,
                    'event_type' => $event->type,
                    'event_id' => $event->id,
                    'occurrence_id' => $dispatch->occurrence_id,
                ],
            ]);

            $event->update([
                'status' => EventInboxStatus::PROCESSED->value,
                'processed_at' => now(),
            ]);
        });
    }
}

