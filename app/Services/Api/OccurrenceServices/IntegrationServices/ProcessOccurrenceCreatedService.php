<?php

namespace App\Services\Api\OccurrenceServices\IntegrationServices;

use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Enums\EventInboxEnums\EventInboxType;
use App\Enums\OccurrenceEnums\OccurrenceStatus;
use App\Models\AuditLog;
use App\Models\EventInbox;
use App\Models\Occurrence;
use App\Support\Database\DatabaseErrorHelper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ProcessOccurrenceCreatedService
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

            $payloadData = $event->payload;

            try {
                $occurrence = Occurrence::create([
                    'external_id' => $payloadData['externalId'],
                    'type' => $payloadData['type'],
                    'status' => OccurrenceStatus::REPORTED->value,
                    'description' => $payloadData['description'],
                    'reported_at' => $payloadData['reportedAt'],
                ]);

                $wasCreated = true;

            } catch (QueryException $exception) {
                if (! DatabaseErrorHelper::isUniqueViolation($exception))
                {
                    throw $exception;
                }

                $occurrence = Occurrence::where(
                    'external_id',
                    $payloadData['externalId']
                )->first();

                if (! $occurrence)
                {
                    throw $exception;
                }
            }

            if ($wasCreated)
            {
                AuditLog::create([
                    'entity_type' => Occurrence::class,
                    'entity_id' => $occurrence->id,
                    'action' => EventInboxType::CREATED->value,
                    'before' => null,
                    'after' => $occurrence->toArray(),
                    'meta' => [
                        'source' => $event->source,
                        'event_type' => $event->type,
                        'event_id' => $event->id,
                    ]
                ]);
            }

            $event->update([
                'status' => EventInboxStatus::PROCESSED->value,
                'processed_at' => now(),
            ]);
        });
    }
}
