<?php

namespace App\Http\Middleware;

use App\Constant\AccountStatusConstant;
use App\Constant\KioskRegistrationSettingConstant;
use App\Repositories\Account\AccountRepository;
use App\Services\Account\AccountSystemSettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the gym for a public (unauthenticated) request from the ULID
 * public_code in the URL, and stash it on the request.
 *
 * Every other route in this API derives account_id from the authenticated
 * user; these routes have no user, so this is the single place tenant identity
 * is established for them. Downstream code reads
 * $request->attributes->get('public_account') and must never reach for
 * userData.
 */
class ResolvePublicAccountMiddleware
{
    /**
     * @param AccountRepository $accountRepository
     * @param AccountSystemSettingService $settingService
     */
    public function __construct(
        private AccountRepository $accountRepository,
        private AccountSystemSettingService $settingService,
    ) {
    }

    /**
     * Resolve and attach the account, or 404.
     *
     * Unknown code, deactivated account, and registration-disabled all return
     * the SAME 404 body on purpose. A distinct "registration is disabled"
     * response would confirm that a code is real, handing anyone probing the
     * endpoint a free oracle for discovering valid gyms.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $publicCode = (string) $request->route('publicCode');
        $account = $publicCode === '' ? null : $this->accountRepository->findAccountByPublicCode($publicCode);

        if (!$account || $account->status !== AccountStatusConstant::STATUS_ACTIVE) {
            return $this->notFound();
        }

        $enabled = $this->settingService->get(
            $account->id,
            KioskRegistrationSettingConstant::definitions()['kiosk_registration_enabled']['camel']
        );

        if (!$enabled) {
            return $this->notFound();
        }

        $request->attributes->set('public_account', $account);

        return $next($request);
    }

    /**
     * The single indistinguishable "no such registration link" response.
     *
     * @return Response
     */
    private function notFound(): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'This registration link is not available.',
        ], 404);
    }
}
