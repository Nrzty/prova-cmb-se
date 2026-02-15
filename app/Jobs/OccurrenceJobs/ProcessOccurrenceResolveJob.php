<?php

namespace App\Jobs\OccurrenceJobs;

use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Models\EventInbox;
use App\Services\Api\OccurrenceLifecycleServices\ProcessOccurrenceResolveService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOccurrenceResolveJob implements ShouldQueue
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

    public function handle(ProcessOccurrenceResolveService $processOccurrenceResolveService): void
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

            $processOccurrenceResolveService->process($this->eventInboxId);

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
