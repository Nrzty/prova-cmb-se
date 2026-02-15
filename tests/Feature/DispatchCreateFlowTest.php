<?php

namespace Tests\Feature;

use App\Enums\DispatchEnums\DispatchStatus;
use App\Enums\EventInboxEnums\EventInboxSource;
use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Enums\EventInboxEnums\EventInboxType;
use App\Enums\OccurrenceEnums\OccurrenceStatus;
use App\Models\AuditLog;
use App\Models\Dispatche;
use App\Models\EventInbox;
use App\Models\Occurrence;
use App\Services\Api\Dispatch\ProcessDispatchCreatedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchCreateFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_criar_dispatch_registra_comando_idempotente(): void
    {
        $occurrence = Occurrence::factory()->create([
            'status' => OccurrenceStatus::REPORTED->value,
        ]);

        $headers = [
            'X-API-Key' => 'eu-vou-passar',
            'Idempotency-Key' => 'idem-dispatch-1',
        ];

        $body = [
            'resourceCode' => 'ABT-12',
        ];

        $first = $this->withHeaders($headers)
            ->postJson("/api/occurrences/{$occurrence->id}/dispatches", $body)
            ->assertAccepted();

        $commandId1 = $first->json('commandId');

        $second = $this->withHeaders($headers)
            ->postJson("/api/occurrences/{$occurrence->id}/dispatches", $body)
            ->assertAccepted();

        $commandId2 = $second->json('commandId');

        $this->assertNotEmpty($commandId1);
        $this->assertEquals($commandId1, $commandId2);

        $this->assertDatabaseCount('event_inboxes', 1);
        $this->assertDatabaseHas('event_inboxes', [
            'id' => $commandId1,
            'type' => EventInboxType::DISPATCH_CREATED->value,
            'source' => EventInboxSource::WEB_OPERATOR->value,
        ]);
    }

    public function test_processor_cria_dispatch_audita_e_marca_inbox_processed(): void
    {
        $occurrence = Occurrence::factory()->create([
            'status' => OccurrenceStatus::REPORTED->value,
        ]);

        $eventInbox = EventInbox::create([
            'idempotency_key' => 'idem-dispatch-processor',
            'source' => EventInboxSource::WEB_OPERATOR->value,
            'type' => EventInboxType::DISPATCH_CREATED->value,
            'payload' => [
                'occurrenceId' => $occurrence->id,
                'resourceCode' => 'ABT-12',
            ],
            'status' => EventInboxStatus::PENDING->value,
        ]);

        app(ProcessDispatchCreatedService::class)->process($eventInbox->id);

        $eventInbox->refresh();

        $this->assertEquals(EventInboxStatus::PROCESSED->value, $eventInbox->status);
        $this->assertNotNull($eventInbox->processed_at);

        $dispatch = Dispatche::where('occurrence_id', $occurrence->id)
            ->where('resource_code', 'ABT-12')
            ->firstOrFail();

        $this->assertEquals(DispatchStatus::ASSIGNED->value, $dispatch->status);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Dispatche::class,
            'entity_id' => $dispatch->id,
            'action' => EventInboxType::DISPATCH_CREATED->value,
        ]);

        $audit = AuditLog::where('entity_id', $dispatch->id)->firstOrFail();
        $this->assertEquals($eventInbox->id, $audit->meta['event_id'] ?? null);
    }

    public function test_concorrencia_simulada_mesmo_inbox_nao_duplica_dispatch_nem_audit(): void
    {
        $occurrence = Occurrence::factory()->create([
            'status' => OccurrenceStatus::REPORTED->value,
        ]);

        $eventInbox = EventInbox::create([
            'idempotency_key' => 'idem-dispatch-concurrency',
            'source' => EventInboxSource::WEB_OPERATOR->value,
            'type' => EventInboxType::DISPATCH_CREATED->value,
            'payload' => [
                'occurrenceId' => $occurrence->id,
                'resourceCode' => 'ABT-12',
            ],
            'status' => EventInboxStatus::PENDING->value,
        ]);

        $service = app(ProcessDispatchCreatedService::class);
        $service->process($eventInbox->id);
        $service->process($eventInbox->id);

        $this->assertDatabaseCount('dispatches', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }
}

