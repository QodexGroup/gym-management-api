<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply idempotency to POST and PUT methods
        if (!in_array($request->method(), ['POST', 'PUT'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        // If no key provided, process normally
        if (!$idempotencyKey) {
            return $next($request);
        }

        // Build cache key: user + route + idempotency key
        $userId = optional($request->user())->id ?? 'guest';
        $route = $request->path();
        $method = $request->method();
        $cacheKey = "idempotency:{$userId}:{$method}:{$route}:{$idempotencyKey}";

        // Check if we've already processed this exact request
        if (Cache::has($cacheKey)) {
            // Return cached response (same response as first request)
            return Cache::get($cacheKey);
        }

        // Process the request
        $response = $next($request);

        // Only cache successful responses (2xx and 201)
        if ($response->isSuccessful() || $response->getStatusCode() === 201) {
            // Store full response for 5 minutes
            Cache::put($cacheKey, $response, now()->addMinutes(5));
        }

        return $response;
    }
}
