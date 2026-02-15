<?php

namespace App\Services\Api\OccurrenceLifecycleServices;

use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Enums\EventInboxEnums\EventInboxType;
use App\Enums\OccurrenceEnums\OccurrenceStatus;
use App\Models\AuditLog;
use App\Models\EventInbox;
use App\Models\Occurrence;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProcessOccurrenceStartService
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

            if ($event->type !== EventInboxType::STARTED->value)
            {
                throw new LogicException('EventInboxType inválido para ProcessOccurrenceStartService.');
            }

            $occurrenceId = $event->payload['occurrenceId'] ?? null;
            if (! $occurrenceId)
            {
                throw new LogicException('Payload inválido: occurrenceId ausente.');
            }

            $occurrence = Occurrence::where('id', $occurrenceId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($occurrence->status !== OccurrenceStatus::REPORTED->value)
            {
                throw new LogicException('Transição inválida: ocorrência não está em reported.');
            }

            $before = $occurrence->toArray();

            $occurrence->update([
                'status' => OccurrenceStatus::IN_PROGRESS->value,
            ]);

            AuditLog::create([
                'entity_type' => Occurrence::class,
                'entity_id' => $occurrence->id,
                'action' => $event->type,
                'before' => $before,
                'after' => $occurrence->fresh()->toArray(),
                'meta' => [
                    'source' => $event->source,
                    'event_type' => $event->type,
                    'event_id' => $event->id,
                ],
            ]);

            $event->update([
                'status' => EventInboxStatus::PROCESSED->value,
                'processed_at' => now(),
            ]);
        });
    }
}
