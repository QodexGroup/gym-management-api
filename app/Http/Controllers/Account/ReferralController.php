<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Account\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(
        private ReferralService $referralService
    ) {
    }

    /**
     * Referral summary for the authenticated account owner.
     * Auto-creates the account's referral code on first call.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user');
        if (!$user) {
            return ApiResponse::error('User not found', 404);
        }

        $summary = $this->referralService->getReferralSummary((int) $user->account_id);

        return ApiResponse::success($summary, 'Referral summary retrieved successfully');
    }
}
