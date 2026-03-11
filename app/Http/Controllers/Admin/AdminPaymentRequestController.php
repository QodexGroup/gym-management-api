<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ApiResponse;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\AccountSubscription\AccountPaymentRequestResource;
use App\Repositories\Admin\AdminPaymentRequestRepository;
use Illuminate\Http\JsonResponse;

class AdminPaymentRequestController
{
    public function __construct(
        private AdminPaymentRequestRepository $adminPaymentRequestRepository
    ) {
    }

    /**
     * List pending payment requests (platform admin only). Approve/reject via Artisan command.
     */
    public function getPendingPaymentRequests(GenericRequest $request): JsonResponse
    {
        $requests = $this->adminPaymentRequestRepository->getPendingPaymentRequests();

        return ApiResponse::success(AccountPaymentRequestResource::collection($requests));
    }
}
