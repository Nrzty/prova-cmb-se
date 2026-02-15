<?php

namespace App\Jobs\OccurrenceJobs;

use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Models\EventInbox;
use App\Services\Api\OccurrenceLifecycleServices\ProcessOccurrenceStartService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOccurrenceStartJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(
        private string $eventInboxId,
    ) { }

    public function handle(ProcessOccurrenceStartService $processOccurrenceStartService): void
    {
        $event = EventInbox::find($this->eventInboxId);

        if (! $event)
        {
            return;
        }

        if ($event->status !== EventInboxStatus::PENDING->value)
        {
            return;
        }

        try {
            logger()->info('Processing event inbox', [
                'event_inbox_id' => $event->id,
                'type' => $event->type,
                'source' => $event->source,
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
            ]);

            $processOccurrenceStartService->process($this->eventInboxId);

            logger()->info('Processed event inbox', [
                'event_inbox_id' => $event->id,
                'type' => $event->type,
                'source' => $event->source,
            ]);
        } catch (Exception $exception) {
            logger()->error('Failed processing event inbox', [
                'event_inbox_id' => $event->id,
                'type' => $event->type,
                'source' => $event->source,
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
                'error' => $exception->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $event->update([
                    'status' => EventInboxStatus::FAILED->value,
                    'error' => $exception->getMessage(),
                    'processed_at' => now(),
                ]);

                return;
            }

            throw $exception;
        }
    }
}
