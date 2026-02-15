<?php

namespace Tests\Feature;

use App\Enums\EventInboxEnums\EventInboxSource;
use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Enums\EventInboxEnums\EventInboxType;
use App\Models\AuditLog;
use App\Models\EventInbox;
use App\Models\Occurrence;
use App\Services\Api\OccurrenceServices\IntegrationServices\ProcessOccurrenceCreatedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessOccurrenceCreatedFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_processa_event_inbox_e_cria_occurrence_audit_e_marca_processed(): void
    {
        $payload = [
            'externalId' => 'EXT-2026-000999',
            'type' => 'incendio_urbano',
            'description' => 'Incêndio em residência',
            'reportedAt' => '2026-02-01T14:32:00-03:00',
        ];

        $eventInbox = EventInbox::create([
            'idempotency_key' => 'key-123',
            'source' => EventInboxSource::EXTERNAL_SYSTEM->value,
            'type' => EventInboxType::CREATED->value,
            'payload' => $payload,
            'status' => EventInboxStatus::PENDING->value,
        ]);

        app(ProcessOccurrenceCreatedService::class)->process($eventInbox->id);

        $eventInbox->refresh();

        $this->assertEquals(EventInboxStatus::PROCESSED->value, $eventInbox->status);
        $this->assertNotNull($eventInbox->processed_at);

        $this->assertDatabaseHas('occurrences', [
            'external_id' => $payload['externalId'],
        ]);

        $occurrence = Occurrence::where('external_id', $payload['externalId'])->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Occurrence::class,
            'entity_id' => $occurrence->id,
            'action' => EventInboxType::CREATED->value,
        ]);

        $audit = AuditLog::where('entity_id', $occurrence->id)->firstOrFail();

        $this->assertEquals($eventInbox->id, $audit->meta['event_id'] ?? null);
        $this->assertEquals($eventInbox->type, $audit->meta['event_type'] ?? null);
        $this->assertEquals($eventInbox->source, $audit->meta['source'] ?? null);
    }
}

