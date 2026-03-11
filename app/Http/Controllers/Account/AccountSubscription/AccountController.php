<?php

namespace App\Http\Controllers\Account\AccountSubscription;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AccountRequest;
use App\Http\Resources\Account\AccountResource;
use App\Repositories\Account\AccountRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class AccountController extends Controller
{
    public function __construct(
        private AccountRepository $accountRepository
    ) {
    }

    /**
     * Get the authenticated user's account with active subscription
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAccount(Request $request): JsonResponse
    {
        try {
            $user = $request->attributes->get('user');

            if (!$user) {
                return ApiResponse::error('User not found', 404);
            }

            // Load account with active subscription plan
            if (!$user->relationLoaded('account')) {
                $user->load('account.activeAccountSubscriptionPlan.subscriptionPlan');
            }

            if (!$user->account) {
                return ApiResponse::error('Account not found', 404);
            }

            return ApiResponse::success(
                new AccountResource($user->account),
                'Account retrieved successfully'
            );

        } catch (\Exception $e) {
            return ApiResponse::error('Error fetching account data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update account information (including billing)
     *
     * @param AccountRequest $request
     * @return JsonResponse
     */
    public function updateAccount(AccountRequest $request): JsonResponse
    {
        try {
            $user = $request->attributes->get('user');

            if (!$user || !$user->account) {
                return ApiResponse::error('Account not found', 404);
            }

            $account = $this->accountRepository->updateAccount($user->account->id, $request->validated());

            // Reload relationships
            $account->load('activeAccountSubscriptionPlan.subscriptionPlan');

            return ApiResponse::success(
                new AccountResource($account),
                'Account updated successfully'
            );

        } catch (\Exception $e) {
            return ApiResponse::error('Error updating account: ' . $e->getMessage(), 500);
        }
    }
}
