<?php

namespace Tests\Feature;

use App\Enums\EventInboxEnums\EventInboxSource;
use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Enums\EventInboxEnums\EventInboxType;
use App\Enums\OccurrenceEnums\OccurrenceStatus;
use App\Jobs\OccurrenceJobs\ProcessOccurrenceResolveJob;
use App\Jobs\OccurrenceJobs\ProcessOccurrenceStartJob;
use App\Models\AuditLog;
use App\Models\EventInbox;
use App\Models\Occurrence;
use App\Services\Api\OccurrenceLifecycleServices\ProcessOccurrenceResolveService;
use App\Services\Api\OccurrenceLifecycleServices\ProcessOccurrenceStartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OccurrenceLifecycleStartResolveTest extends TestCase
{
    use RefreshDatabase;

    private array $headers = [
        'X-API-Key' => 'eu-vou-passar',
        'Idempotency-Key' => 'eu-vou-passar',
    ];

    public function test_start_endpoint_registra_comando_idempotente(): void
    {
        $occurrence = Occurrence::factory()->create([
            'status' => OccurrenceStatus::REPORTED->value,
        ]);

        $first = $this->withHeaders($this->headers)
            ->postJson("/api/occurrences/{$occurrence->id}/start")
            ->assertAccepted();

        $commandId1 = $first->json('commandId');

        $second = $this->withHeaders($this->headers)
            ->postJson("/api/occurrences/{$occurrence->id}/start")
            ->assertAccepted();

        $commandId2 = $second->json('commandId');

        $this->assertNotEmpty($commandId1);
        $this->assertEquals($commandId1, $commandId2);

        $this->assertDatabaseCount('event_inboxes', 1);
        $this->assertDatabaseHas('event_inboxes', [
            'id' => $commandId1,
            'type' => EventInboxType::STARTED->value,
            'source' => EventInboxSource::WEB_OPERATOR->value,
        ]);
    }

    public function test_start_processor_muda_status_e_gera_audit_e_marca_inbox_processed(): void
    {
        $occurrence = Occurrence::factory()->create([
            'status' => OccurrenceStatus::REPORTED->value,
        ]);

        $eventInbox = EventInbox::create([
            'idempotency_key' => 'idem-start-1',
            'source' => EventInboxSource::WEB_OPERATOR->value,
            'type' => EventInboxType::STARTED->value,
            'payload' => ['occurrenceId' => $occurrence->id],
            'status' => EventInboxStatus::PENDING->value,
        ]);

        app(ProcessOccurrenceStartService::class)->process($eventInbox->id);

        $occurrence->refresh();
        $eventInbox->refresh();

        $this->assertEquals(OccurrenceStatus::IN_PROGRESS->value, $occurrence->status);
        $this->assertEquals(EventInboxStatus::PROCESSED->value, $eventInbox->status);
        $this->assertNotNull($eventInbox->processed_at);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Occurrence::class,
            'entity_id' => $occurrence->id,
            'action' => EventInboxType::STARTED->value,
        ]);

        $audit = AuditLog::where('entity_id', $occurrence->id)->firstOrFail();
        $this->assertEquals($eventInbox->id, $audit->meta['event_id'] ?? null);
    }

    public function test_resolve_processor_muda_status_e_gera_audit_e_marca_inbox_processed(): void
    {
        $occurrence = Occurrence::factory()->create([
            'status' => OccurrenceStatus::IN_PROGRESS->value,
        ]);

        $eventInbox = EventInbox::create([
            'idempotency_key' => 'idem-resolve-1',
            'source' => EventInboxSource::WEB_OPERATOR->value,
            'type' => EventInboxType::RESOLVED->value,
            'payload' => ['occurrenceId' => $occurrence->id],
            'status' => EventInboxStatus::PENDING->value,
        ]);

        app(ProcessOccurrenceResolveService::class)->process($eventInbox->id);

        $occurrence->refresh();
        $eventInbox->refresh();

        $this->assertEquals(OccurrenceStatus::RESOLVED->value, $occurrence->status);
        $this->assertEquals(EventInboxStatus::PROCESSED->value, $eventInbox->status);
        $this->assertNotNull($eventInbox->processed_at);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Occurrence::class,
            'entity_id' => $occurrence->id,
            'action' => EventInboxType::RESOLVED->value,
        ]);
    }

    public function test_resolve_invalido_deve_falhar_e_nao_mudar_status(): void
    {
        $occurrence = Occurrence::factory()->create([
            'status' => OccurrenceStatus::REPORTED->value,
        ]);

        $eventInbox = EventInbox::create([
            'idempotency_key' => 'idem-resolve-invalid',
            'source' => EventInboxSource::WEB_OPERATOR->value,
            'type' => EventInboxType::RESOLVED->value,
            'payload' => ['occurrenceId' => $occurrence->id],
            'status' => EventInboxStatus::PENDING->value,
        ]);

        $this->expectException(\LogicException::class);

        app(ProcessOccurrenceResolveService::class)->process($eventInbox->id);

        $occurrence->refresh();
        $this->assertEquals(OccurrenceStatus::REPORTED->value, $occurrence->status);
    }

    public function test_concorrencia_simulada_processar_mesmo_inbox_duas_vezes_nao_duplica_auditoria(): void
    {
        $occurrence = Occurrence::factory()->create([
            'status' => OccurrenceStatus::REPORTED->value,
        ]);

        $eventInbox = EventInbox::create([
            'idempotency_key' => (string) Str::uuid(),
            'source' => EventInboxSource::WEB_OPERATOR->value,
            'type' => EventInboxType::STARTED->value,
            'payload' => ['occurrenceId' => $occurrence->id],
            'status' => EventInboxStatus::PENDING->value,
        ]);

        $service = app(ProcessOccurrenceStartService::class);
        $service->process($eventInbox->id);
        $service->process($eventInbox->id);

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'entity_id' => $occurrence->id,
            'action' => EventInboxType::STARTED->value,
        ]);
    }
}

