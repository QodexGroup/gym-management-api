<?php

namespace App\Http\Controllers\Public;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Account\Account;
use App\Services\Public\PublicRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only public information about a gym, for rendering the registration page.
 *
 * Unauthenticated. The account is resolved from the URL public_code by
 * ResolvePublicAccountMiddleware.
 */
class PublicGymController extends Controller
{
    /**
     * @param PublicRegistrationService $publicRegistrationService
     */
    public function __construct(
        private PublicRegistrationService $publicRegistrationService,
    ) {
    }

    /**
     * Return the gym name, copy, and which optional fields are mandatory.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getGymRegistrationInfo(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->attributes->get('public_account');

        return ApiResponse::success($this->publicRegistrationService->getGymRegistrationInfo($account));
    }
}
