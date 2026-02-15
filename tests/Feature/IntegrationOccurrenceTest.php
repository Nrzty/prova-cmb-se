<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class IntegrationOccurrenceTest extends TestCase
{
    use RefreshDatabase;
    /**
    * Exemplos simples de testes para Receber ocorrência externa.
    */
    public function test_sem_idempotency_key_deve_retornar_400(): void
    {
        $payload = [
            'externalId' => 'EXT-2026-000123',
            'type' => 'incendio_urbano',
            'description' => 'Incêndio em residência',
            'reportedAt' => '2026-02-01T14:32:00-03:00',
        ];

        $this->withHeaders([
            'X-API-Key' => 'eu-vou-passar',
        ])->postJson('/api/integrations/occurrences', $payload)
            ->assertUnprocessable();
    }

    public function test_com_payload_invalido_deve_retornar_422(): void
    {
        $payload = [
            'externalId' => 'EXT-2026-000123',
        ];

        $this->withHeaders([
            'X-API-Key' => 'eu-vou-passar',
            'Idempotency-Key' => 'eu-vou-passar',
        ])->postJson('/api/integrations/occurrences', $payload)
            ->assertUnprocessable();
    }

    public function test_com_payload_valido_deve_retornar_202(): void
    {
        $payload = [
            'externalId' => 'EXT-2026-000123',
            'type' => 'incendio_urbano',
            'description' => 'Incêndio em residência',
            'reportedAt' => '2026-02-01T14:32:00-03:00',
        ];

        $this->withHeaders([
            'X-API-Key' => 'eu-vou-passar',
            'Idempotency-Key' => 'eu-vou-passar',
        ])->postJson('/api/integrations/occurrences', $payload)
            ->assertAccepted()
            ->assertJsonStructure([
                'commandId',
                'status',
            ]);
    }

    public function test_mesma_key_e_mesmo_payload_nao_deve_duplicar(): void
    {
        $payload = [
            'externalId' => 'EXT-2026-000123',
            'type' => 'incendio_urbano',
            'description' => 'Incêndio em residência',
            'reportedAt' => '2026-02-01T14:32:00-03:00',
        ];

        $headers = [
            'X-API-Key' => 'eu-vou-passar',
            'Idempotency-Key' => 'eu-vou-passar',
        ];

        $firstResponse = $this->withHeaders($headers)
            ->postJson('/api/integrations/occurrences', $payload)
            ->assertAccepted();

        $commandId = $firstResponse->json('commandId');

        $secondResponse = $this->withHeaders($headers)
            ->postJson('/api/integrations/occurrences', $payload)
            ->assertAccepted();

        $this->assertEquals($commandId, $secondResponse->json('commandId'));

        $this->assertDatabaseCount('event_inboxes', 1);
    }

    public function test_mesma_key_com_payload_diferente_deve_retornar_409(): void
    {
        $payloadOriginal = [
            'externalId' => 'EXT-2026-000123',
            'type' => 'incendio_urbano',
            'description' => 'Incêndio em residência',
            'reportedAt' => '2026-02-01T14:32:00-03:00',
        ];

        $payloadAlterado = [
            'externalId' => 'EXT-2026-000123',
            'type' => 'incendio_urbano',
            'description' => 'Incêndio em prédio comercial',
            'reportedAt' => '2026-02-01T14:32:00-03:00',
        ];

        $headers = [
            'X-API-Key' => 'eu-vou-passar',
            'Idempotency-Key' => 'eu-vou-passar',
        ];

        $this->withHeaders($headers)
            ->postJson('/api/integrations/occurrences', $payloadOriginal)
            ->assertAccepted();

        $this->withHeaders($headers)
            ->postJson('/api/integrations/occurrences', $payloadAlterado)
            ->assertStatus(409);

        $this->assertDatabaseCount('event_inboxes', 1);
    }
}
