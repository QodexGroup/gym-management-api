<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Http\Requests\Account\SubscriptionRequestRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\AccountSubscriptionRequestResource;
use App\Models\Account\AccountSubscriptionRequest;
use App\Services\Account\AccountSubscriptionRequestService;
use Illuminate\Http\JsonResponse;

class SubscriptionRequestController
{
    public function __construct(
        private AccountSubscriptionRequestService $subscriptionRequestService
    ) {
    }

    /**
     * Get current account's subscription request(s) - most recent first.
     */
    public function index(GenericRequest $request): JsonResponse
    {
        $accountId = $request->getUserData()->account_id;
        $requests = AccountSubscriptionRequest::with(['subscriptionPlan'])
            ->where('account_id', $accountId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return ApiResponse::success(AccountSubscriptionRequestResource::collection($requests));
    }

    /**
     * Owner submits subscription request with receipt.
     */
    public function store(SubscriptionRequestRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $subscriptionRequest = $this->subscriptionRequestService->createRequest($genericData);
            return ApiResponse::success(
                new AccountSubscriptionRequestResource($subscriptionRequest->load(['account', 'subscriptionPlan'])),
                'Subscription request submitted. Your receipt has been received and is pending approval.',
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
