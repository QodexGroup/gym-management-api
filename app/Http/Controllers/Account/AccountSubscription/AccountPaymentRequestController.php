<?php

namespace App\Http\Controllers\Account\AccountSubscription;

use App\Helpers\ApiResponse;
use App\Http\Requests\Account\AccountSubscription\AccountPaymentRequestRequest;
use App\Http\Requests\Account\AccountSubscription\AccountReactivationPaymentRequestRequest;
use App\Http\Requests\Account\AccountSubscription\AccountSubscriptionRequestRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\AccountSubscription\AccountPaymentRequestResource;
use App\Repositories\Account\AccountSubscription\AccountPaymentRequestRepository;
use App\Services\Account\AccountSubscription\AccountPaymentRequestService;
use Illuminate\Http\JsonResponse;

class AccountPaymentRequestController
{
    public function __construct(
        private AccountPaymentRequestService $paymentRequestService,
        private AccountPaymentRequestRepository $requestRepository
    ) {
    }

    /**
     * Get current account's payment request(s) - paginated, most recent first.
     *
     * @param GenericRequest $request
     *
     * @return JsonResponse
     */
    public function getPaymentRequests(GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericData();
        $requests = $this->requestRepository->paginateByAccount($genericData);

        return ApiResponse::success(AccountPaymentRequestResource::collection($requests)->response()->getData(true));
    }

    /**
     * Owner submits payment request with receipt for an invoice.
     *
     * @param AccountPaymentRequestRequest $request
     *
     * @return JsonResponse
     */
    public function createPaymentRequest(AccountPaymentRequestRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $paymentRequest = $this->paymentRequestService->createInvoicePaymentRequest($genericData);

            return ApiResponse::success(new AccountPaymentRequestResource($paymentRequest),
                'Payment request submitted. Your receipt has been received and is pending approval.',
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Owner submits reactivation payment request (no invoice, fixed fee).
     *
     * @param AccountReactivationPaymentRequestRequest $request
     *
     * @return JsonResponse
     */
    public function createReactivationPaymentRequest(AccountReactivationPaymentRequestRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $paymentRequest = $this->paymentRequestService->createReactivationPaymentRequest($genericData);

            return ApiResponse::success(
                new AccountPaymentRequestResource($paymentRequest),
                'Reactivation payment submitted. Pending admin approval.',
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }


    /**
     * Owner updates subscription plan selection (takes effect on next billing cycle).
     *
     * @param AccountSubscriptionRequestRequest $request
     *
     * @return JsonResponse
     */
    public function createSubscriptionRequest(AccountSubscriptionRequestRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $result = $this->paymentRequestService->createSubscriptionRequest($genericData);

            return ApiResponse::success(
                $result,
                $result['message'],
                200
            );
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
