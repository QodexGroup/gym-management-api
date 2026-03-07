<?php

namespace App\Http\Controllers\Account\AccountSubscription;

use App\Helpers\ApiResponse;
use App\Http\Requests\Common\FilterDateRequest;
use App\Http\Requests\Account\AccountSubscription\SubscriptionRequestRequest;
use App\Http\Resources\Account\AccountSubscription\AccountSubscriptionRequestResource;
use App\Repositories\Account\AccountSubscription\AccountSubscriptionRequestRepository;
use App\Services\Account\AccountSubscription\AccountSubscriptionRequestService;
use Illuminate\Http\JsonResponse;

class SubscriptionRequestController
{
    public function __construct(
        private AccountSubscriptionRequestService $subscriptionRequestService,
        private AccountSubscriptionRequestRepository $requestRepository
    ) {
    }

    /**
     * Get current account's subscription request(s) - most recent first.
     */
    public function getSubscriptionRequests(FilterDateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $accountId = $request->getUserData()->account_id;
        $requests = $this->requestRepository->getRecentByAccountId(
            $accountId,
            $validated['startDate'] ?? null,
            $validated['endDate'] ?? null
        );

        return ApiResponse::success(AccountSubscriptionRequestResource::collection($requests));
    }

    /**
     * Owner submits subscription request with receipt.
     */
    public function createSubscriptionRequest(SubscriptionRequestRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $subscriptionRequest = $this->subscriptionRequestService->createRequest($genericData);
            return ApiResponse::success(
                new AccountSubscriptionRequestResource($subscriptionRequest->load(['account', 'invoice.accountSubscriptionPlan.platformPlan'])),
                'Subscription request submitted. Your receipt has been received and is pending approval.',
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
