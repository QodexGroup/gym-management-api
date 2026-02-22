<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('user');

        if (!$user || !$user->email) {
            return ApiResponse::error('Unauthorized', 403);
        }

        $adminEmails = config('app.platform_admin_emails', []);
        if (empty($adminEmails) || !in_array($user->email, $adminEmails, true)) {
            return ApiResponse::error('Forbidden. Platform admin access required.', 403);
        }

        return $next($request);
    }
}
