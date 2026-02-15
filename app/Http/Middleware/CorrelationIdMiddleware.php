<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdMiddleware
{
    public const HEADER = 'X-Correlation-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header(self::HEADER);

        if (! is_string($correlationId) || trim($correlationId) === '')
        {
            $correlationId = (string) Str::uuid();
        }

        $request->attributes->set('correlation_id', $correlationId);
        logger()->withContext(['correlation_id' => $correlationId]);

        $response = $next($request);

        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }
}

