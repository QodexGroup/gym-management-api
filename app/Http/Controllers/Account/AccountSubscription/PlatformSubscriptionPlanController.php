<?php

namespace App\Http\Controllers\Account\AccountSubscription;

use App\Helpers\ApiResponse;
use App\Services\Account\AccountSubscription\PlatformSubscriptionPlanService;
use Illuminate\Http\JsonResponse;

class PlatformSubscriptionPlanController
{
    public function __construct(
        private PlatformSubscriptionPlanService $planService
    ) {
    }

    /**
     * List platform subscription plans (for subscription/upgrade page).
     */
    public function getPlatformSubscriptionPlans(): JsonResponse
    {
        $plans = $this->planService->getPaidPlansAsApiShape();
        return ApiResponse::success($plans->values()->all());
    }
}
