<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Http\Middleware\HandleCors;
use App\Exceptions\QuotaExceededException;
use App\Helpers\ApiResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add CORS to global middleware stack — covers all routes including preflight OPTIONS
        $middleware->prepend(HandleCors::class);

        // Register idempotency middleware alias
        $middleware->alias([
            'idempotent' => IdempotencyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Resource quota breaches (storage, SMS credits, …) map to a 403.
        $exceptions->render(function (QuotaExceededException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        });

        // Report exceptions to New Relic APM so they surface in Errors Inbox.
        // Laravel catches exceptions itself, so the agent never sees them as
        // "uncaught" — we must notify it explicitly. No-op when the agent
        // extension isn't loaded (e.g. local dev), so this is always safe.
        $exceptions->report(function (\Throwable $e): void {
            if (extension_loaded('newrelic') && function_exists('newrelic_notice_error')) {
                newrelic_notice_error($e->getMessage(), $e);
            }
        });
    })
    ->create();

