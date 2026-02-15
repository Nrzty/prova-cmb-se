<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrelationIdMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_deve_gerar_correlation_id_quando_nao_enviado(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'eu-vou-passar',
        ])->get('/api/');

        $response->assertOk();
        $response->assertHeader('X-Correlation-Id');

        $value = $response->headers->get('X-Correlation-Id');
        $this->assertNotEmpty($value);
    }

    public function test_deve_preservar_correlation_id_enviado(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'eu-vou-passar',
            'X-Correlation-Id' => 'abc-123',
        ])->get('/api/');

        $response->assertOk();
        $response->assertHeader('X-Correlation-Id', 'abc-123');
    }

    public function test_deve_retornar_correlation_id_mesmo_em_401(): void
    {
        $response = $this->get('/api/');

        $response->assertUnauthorized();
        $response->assertHeader('X-Correlation-Id');

        $value = $response->headers->get('X-Correlation-Id');
        $this->assertNotEmpty($value);
    }
}
