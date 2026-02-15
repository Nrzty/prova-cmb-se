<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiKeyMiddlewareTest extends TestCase
{
    /**
     * Exemplos simples de testes para o MiddleWare de autenticação.
     */
    public function test_sem_header_deve_retornar_401(): void
    {
        $response = $this->get('/api/');

        $response->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthorized',
            ]);
    }

    public function test_com_header_errado_deve_retornar_401(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'chave-errada',
        ])->get('/api/');

        $response->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthorized',
            ]);
    }

    public function test_com_header_correto_deve_retornar_200(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'eu-vou-passar',
        ])->get('/api/');

        $response->assertOk();
    }
}
