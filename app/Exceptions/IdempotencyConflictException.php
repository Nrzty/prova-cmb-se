<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IdempotencyConflictException extends Exception
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => 'Teste exception',
            'details' => $this->getMessage(),
        ], 409);
    }
}
