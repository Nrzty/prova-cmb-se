<?php

namespace Tests\Feature;

use App\Enums\DispatchEnums\DispatchStatus;
use App\Models\Dispatche;
use App\Models\Occurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchCloseIdempotencyConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_mesma_key_com_payload_diferente_no_close_dispatch_deve_retornar_409(): void
    {
        $occurrence = Occurrence::factory()->create();

        $dispatch1 = Dispatche::create([
            'occurrence_id' => $occurrence->id,
            'resource_code' => 'ABT-12',
            'status' => DispatchStatus::ASSIGNED->value,
        ]);

        $dispatch2 = Dispatche::create([
            'occurrence_id' => $occurrence->id,
            'resource_code' => 'UR-05',
            'status' => DispatchStatus::ASSIGNED->value,
        ]);

        $headers = [
            'X-API-Key' => 'eu-vou-passar',
            'Idempotency-Key' => 'idem-dispatch-close-conflict',
        ];

        $this->withHeaders($headers)
            ->postJson("/api/dispatches/{$dispatch1->id}/close")
            ->assertAccepted();

        // Reutiliza a mesma Idempotency-Key, mas para outro dispatchId.
        $this->withHeaders($headers)
            ->postJson("/api/dispatches/{$dispatch2->id}/close")
            ->assertStatus(409);
    }
}

