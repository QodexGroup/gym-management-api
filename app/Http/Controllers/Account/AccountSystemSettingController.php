<?php

namespace App\Http\Controllers\Account;

use App\Constant\UserStatusConstant;
use App\Helpers\ApiResponse;
use App\Http\Requests\Account\AccountSystemSettingRequest;
use App\Http\Requests\GenericRequest;
use App\Services\Account\AccountSystemSettingService;
use Illuminate\Http\JsonResponse;

/**
 * Single generic endpoint for all per-account settings (membership, and any future
 * groups). New settings are added as keys/rules — not as new controllers.
 */
class AccountSystemSettingController
{
    public function __construct(
        private AccountSystemSettingService $service
    ) {
    }

    /**
     * Get the current account's settings (typed, camelCase map).
     *
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function show(GenericRequest $request): JsonResponse
    {
        $user = $request->getUserData();

        return ApiResponse::success($this->service->getForAccount((int) $user->account_id));
    }

    /**
     * Update the current account's settings (admin/owner only).
     *
     * @param AccountSystemSettingRequest $request
     * @return JsonResponse
     */
    public function update(AccountSystemSettingRequest $request): JsonResponse
    {
        $user = $request->getUserData();

        if (($user->role ?? '') !== UserStatusConstant::ADMIN && !$user->is_account_owner) {
            return ApiResponse::error('Only an administrator can change account settings.', 403);
        }

        $settings = $this->service->update((int) $user->account_id, $request->validated());

        return ApiResponse::success($settings, 'Settings updated successfully');
    }
}
