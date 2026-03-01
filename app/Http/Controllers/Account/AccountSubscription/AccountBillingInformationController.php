<?php

namespace App\Http\Controllers\Account\AccountSubscription;

use App\Helpers\ApiResponse;
use App\Http\Requests\Account\AccountSubscription\AccountBillingInformationRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\AccountSubscription\AccountBillingInformationResource;
use App\Services\Account\AccountSubscription\AccountBillingInformationService;
use Illuminate\Http\JsonResponse;

class AccountBillingInformationController
{
    public function __construct(
        private AccountBillingInformationService $billingService
    ) {
    }

    /**
     * Get current account's billing information.
     */
    public function show(GenericRequest $request): JsonResponse
    {
        $accountId = $request->getUserData()->account_id;
        $billing = $this->billingService->getByAccountId($accountId);
        return ApiResponse::success($billing ? new AccountBillingInformationResource($billing) : null);
    }

    /**
     * Update or create billing information for the current account.
     */
    public function update(AccountBillingInformationRequest $request): JsonResponse
    {
        $accountId = $request->getUserData()->account_id;
        $data = $request->getBillingDataForService();
        $billing = $this->billingService->updateOrCreate($accountId, $data);
        return ApiResponse::success(new AccountBillingInformationResource($billing), 'Billing information saved.');
    }
}
