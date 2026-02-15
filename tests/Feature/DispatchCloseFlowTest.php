<?php

namespace Tests\Feature;

use App\Enums\DispatchEnums\DispatchStatus;
use App\Enums\EventInboxEnums\EventInboxSource;
use App\Enums\EventInboxEnums\EventInboxStatus;
use App\Enums\EventInboxEnums\EventInboxType;
use App\Models\AuditLog;
use App\Models\Dispatche;
use App\Models\EventInbox;
use App\Models\Occurrence;
use App\Services\Api\Dispatch\ProcessDispatchClosedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchCloseFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_fechar_dispatch_registra_comando_idempotente(): void
    {
        $occurrence = Occurrence::factory()->create();
        $dispatch = Dispatche::create([
            'occurrence_id' => $occurrence->id,
            'resource_code' => 'ABT-12',
            'status' => DispatchStatus::ASSIGNED->value,
        ]);

        $headers = [
            'X-API-Key' => 'eu-vou-passar',
            'Idempotency-Key' => 'idem-dispatch-close-1',
        ];

        $first = $this->withHeaders($headers)
            ->postJson("/api/dispatches/{$dispatch->id}/close")
            ->assertAccepted();

        $commandId1 = $first->json('commandId');

        $second = $this->withHeaders($headers)
            ->postJson("/api/dispatches/{$dispatch->id}/close")
            ->assertAccepted();

        $commandId2 = $second->json('commandId');

        $this->assertNotEmpty($commandId1);
        $this->assertEquals($commandId1, $commandId2);

        $this->assertDatabaseCount('event_inboxes', 1);
        $this->assertDatabaseHas('event_inboxes', [
            'id' => $commandId1,
            'type' => EventInboxType::DISPATCH_CLOSED->value,
            'source' => EventInboxSource::WEB_OPERATOR->value,
        ]);
    }

    public function test_processor_fecha_dispatch_audita_e_marca_inbox_processed(): void
    {
        $occurrence = Occurrence::factory()->create();
        $dispatch = Dispatche::create([
            'occurrence_id' => $occurrence->id,
            'resource_code' => 'ABT-12',
            'status' => DispatchStatus::ASSIGNED->value,
        ]);

        $eventInbox = EventInbox::create([
            'idempotency_key' => 'idem-dispatch-close-processor',
            'source' => EventInboxSource::WEB_OPERATOR->value,
            'type' => EventInboxType::DISPATCH_CLOSED->value,
            'payload' => [
                'dispatchId' => $dispatch->id,
            ],
            'status' => EventInboxStatus::PENDING->value,
        ]);

        app(ProcessDispatchClosedService::class)->process($eventInbox->id);

        $dispatch->refresh();
        $eventInbox->refresh();

        $this->assertEquals(DispatchStatus::CLOSED->value, $dispatch->status);
        $this->assertEquals(EventInboxStatus::PROCESSED->value, $eventInbox->status);
        $this->assertNotNull($eventInbox->processed_at);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Dispatche::class,
            'entity_id' => $dispatch->id,
            'action' => EventInboxType::DISPATCH_CLOSED->value,
        ]);

        $audit = AuditLog::where('entity_id', $dispatch->id)->firstOrFail();
        $this->assertEquals($eventInbox->id, $audit->meta['event_id'] ?? null);
    }

    public function test_processor_close_em_dispatch_ja_fechado_nao_duplica_auditoria_e_marca_inbox_processed(): void
    {
        $occurrence = Occurrence::factory()->create();
        $dispatch = Dispatche::create([
            'occurrence_id' => $occurrence->id,
            'resource_code' => 'ABT-12',
            'status' => DispatchStatus::CLOSED->value,
        ]);

        $eventInbox = EventInbox::create([
            'idempotency_key' => 'idem-dispatch-close-already-closed',
            'source' => EventInboxSource::WEB_OPERATOR->value,
            'type' => EventInboxType::DISPATCH_CLOSED->value,
            'payload' => [
                'dispatchId' => $dispatch->id,
            ],
            'status' => EventInboxStatus::PENDING->value,
        ]);

        app(ProcessDispatchClosedService::class)->process($eventInbox->id);

        $eventInbox->refresh();

        $this->assertEquals(EventInboxStatus::PROCESSED->value, $eventInbox->status);
        $this->assertNotNull($eventInbox->processed_at);

        // Não deve criar audit adicional.
        $this->assertDatabaseCount('audit_logs', 0);
    }
}

