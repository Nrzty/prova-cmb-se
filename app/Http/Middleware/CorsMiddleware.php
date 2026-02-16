<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
     public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = config('cors.allowed_origins');
        $allowedOriginsPatterns = config('cors.allowed_origins_patterns', []);
        $requestOrigin = $request->header('Origin');

        $isOriginAllowed = in_array($requestOrigin, $allowedOrigins);

        if (!$isOriginAllowed) {
            foreach ($allowedOriginsPatterns as $pattern) {
                if (preg_match($pattern, $requestOrigin)) {
                    $isOriginAllowed = true;
                    break;
                }
            }
        }

        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflightRequest($request, $requestOrigin, $isOriginAllowed);
        }

        $response = $next($request);

        if ($isOriginAllowed) {
            $response->header('Access-Control-Allow-Origin', $requestOrigin);
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Access-Control-Expose-Headers', implode(', ', config('cors.exposed_headers', [])));
        }

        return $response;
    }

    private function handlePreflightRequest(Request $request, string $requestOrigin, bool $isOriginAllowed): Response
    {
        $response = response('', 204);

        if ($isOriginAllowed) {
            $response->header('Access-Control-Allow-Origin', $requestOrigin);
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Access-Control-Allow-Methods', implode(', ', config('cors.allowed_methods')));
            $response->header('Access-Control-Allow-Headers', implode(', ', config('cors.allowed_headers')));
            $response->header('Access-Control-Max-Age', config('cors.max_age', 0));
        }

        return $response;
    }
}

