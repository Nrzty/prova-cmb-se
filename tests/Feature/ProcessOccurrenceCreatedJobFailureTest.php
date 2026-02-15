<?php

namespace Tests\Feature;

use App\Enums\EventInboxEnums\EventInboxSource;
use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Enums\EventInboxEnums\EventInboxType;
use App\Jobs\OccurrenceJobs\ProcessOccurrenceCreatedJob;
use App\Models\EventInbox;
use App\Services\Api\OccurrenceServices\IntegrationServices\ProcessOccurrenceCreatedService;
use Exception;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProcessOccurrenceCreatedJobFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_marca_failed_e_salva_error_na_ultima_tentativa(): void
    {
        $eventInbox = EventInbox::create([
            'idempotency_key' => 'key-err',
            'source' => EventInboxSource::EXTERNAL_SYSTEM->value,
            'type' => EventInboxType::CREATED->value,
            'payload' => [
                'externalId' => 'EXT-FAIL-0001',
                'type' => 'incendio_urbano',
                'description' => 'x',
                'reportedAt' => '2026-02-01T14:32:00-03:00',
            ],
            'status' => EventInboxStatus::PENDING->value,
        ]);

        $service = Mockery::mock(ProcessOccurrenceCreatedService::class);
        $service->shouldReceive('process')->once()->andThrow(new Exception('boom'));

        $job = new ProcessOccurrenceCreatedJob($eventInbox->id);

        // Simula que o job está na última tentativa.
        $queueJob = Mockery::mock(JobContract::class);
        $queueJob->shouldReceive('attempts')->andReturn(3);
        $job->setJob($queueJob);

        $job->handle($service);

        $eventInbox->refresh();

        $this->assertEquals(EventInboxStatus::FAILED->value, $eventInbox->status);
        $this->assertEquals('boom', $eventInbox->error);
        $this->assertNotNull($eventInbox->processed_at);
    }
}
