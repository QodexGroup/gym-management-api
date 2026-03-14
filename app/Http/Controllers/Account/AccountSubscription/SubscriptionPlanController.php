<?php

namespace App\Http\Controllers\Account\AccountSubscription;

use App\Helpers\ApiResponse;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\AccountSubscription\SubscriptionPlanResource;
use App\Repositories\Account\AccountSubscription\SubscriptionPlanRepository;
use Illuminate\Http\JsonResponse;

class SubscriptionPlanController
{
    public function __construct(
        private SubscriptionPlanRepository $planRepository
    ) {
    }

    /**
     * List subscription plans (for subscription/upgrade page).
     *
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getSubscriptionPlans(GenericRequest $request): JsonResponse
    {
        $plans = $this->planRepository->getPaidPlansOrderedByPrice();
        return ApiResponse::success(SubscriptionPlanResource::collection($plans));
    }
}
