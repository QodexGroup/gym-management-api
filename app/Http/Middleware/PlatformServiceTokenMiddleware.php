<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the operations console — a machine, not a person.
 *
 * Deliberately independent of FirebaseAuthMiddleware and of any tenant scoping:
 * the console reads across every account, so no `account_id` filter applies to
 * anything behind this gate. That makes the service token the entire security
 * boundary, which is why the comparison is constant-time and the token is only
 * ever held hashed.
 *
 * Also unpacks `X-Platform-Actor` so the product can record WHICH operator made
 * a decision. That header is attribution, never authorisation — it is trusted
 * only because the request already proved it holds the service token.
 */
class PlatformServiceTokenMiddleware
{
    /** Request attribute holding the console operator's display string. */
    public const ACTOR_ATTRIBUTE = 'platformActor';

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('platform.require_https') && !$request->secure()) {
            return ApiResponse::error('Forbidden.', 403);
        }

        if (!$this->addressAllowed($request)) {
            Log::warning('Platform request from a disallowed address', ['ip' => $request->ip()]);

            return ApiResponse::error('Forbidden.', 403);
        }

        if (!$this->tokenValid($request)) {
            return ApiResponse::error('Forbidden.', 403);
        }

        // This surface is JSON-only. Without this, a caller that omits Accept
        // gets Laravel's web behaviour for a validation failure — a 302 to an
        // HTML page. The console follows redirects, so it would read that as a
        // 200 with an empty body and report a REFUSED decision as applied.
        $request->headers->set('Accept', 'application/json');

        $request->attributes->set(self::ACTOR_ATTRIBUTE, $this->actor($request));

        return $next($request);
    }

    /**
     * Constant-time comparison against the stored hash.
     *
     * An unconfigured hash fails closed. It is logged because a silent 403 on
     * a freshly deployed environment is otherwise very hard to diagnose.
     *
     * @param Request $request
     *
     * @return bool
     */
    private function tokenValid(Request $request): bool
    {
        $token = $request->bearerToken();
        $expected = config('platform.service_token_hash');

        if (!is_string($expected) || $expected === '') {
            Log::warning('Platform service token hash is not configured; refusing every request.');

            return false;
        }

        if (!is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($expected, hash('sha256', $token));
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    private function addressAllowed(Request $request): bool
    {
        $allowlist = (array) config('platform.ip_allowlist', []);

        return $allowlist === [] || in_array($request->ip(), $allowlist, true);
    }

    /**
     * Format the console operator for the `platform_actor` column, e.g.
     * "console:7 jomilen@example.com". Returns null when the header is absent
     * or malformed — attribution is best-effort and must never block a read.
     *
     * @param Request $request
     *
     * @return string|null
     */
    private function actor(Request $request): ?string
    {
        $header = $request->header('X-Platform-Actor');

        if (!is_string($header) || $header === '') {
            return null;
        }

        $decoded = json_decode($header, true);

        if (!is_array($decoded)) {
            return null;
        }

        $id = $decoded['id'] ?? null;
        $email = $decoded['email'] ?? null;

        if ($id === null && $email === null) {
            return null;
        }

        return mb_substr(trim('console:'.$id.' '.$email), 0, 191);
    }
}
