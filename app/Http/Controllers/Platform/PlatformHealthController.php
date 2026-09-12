<?php

namespace App\Http\Controllers\Platform;

use App\Helpers\PlatformResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Liveness for the console's "Test connection" button and offline badge.
 *
 * Reaching this endpoint at all proves three things at once: the base URL is
 * right, the service token is accepted, and the app boots. That is exactly why
 * it sits behind the same middleware as everything else rather than being open.
 */
class PlatformHealthController
{
    /**
     * @return JsonResponse
     */
    public function getHealth(): JsonResponse
    {
        return PlatformResponse::item([
            'ok' => true,
            'product' => config('platform.product'),
            'version' => config('platform.version'),
            'serverTime' => Carbon::now()->toIso8601String(),
            'timezone' => config('app.timezone'),
        ]);
    }
}
