<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Requests\GenericRequest;
use App\Constant\AccountSubscriptionRequestConstant;
use App\Http\Resources\Account\AccountSubscription\AccountSubscriptionRequestResource;
use App\Models\Account\AccountSubscriptionRequest;
use Illuminate\Http\JsonResponse;

class AdminSubscriptionRequestController
{
    /**
     * List pending subscription requests (platform admin only). Approve/reject via Artisan command.
     */
    public function index(GenericRequest $request): JsonResponse
    {
        $requests = AccountSubscriptionRequest::with(['account', 'subscriptionPlan', 'requestedByUser'])
            ->where('status', AccountSubscriptionRequestConstant::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success(AccountSubscriptionRequestResource::collection($requests));
    }
}
