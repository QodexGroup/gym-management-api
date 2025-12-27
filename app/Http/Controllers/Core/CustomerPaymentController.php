<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CustomerPaymentRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Core\CustomerPaymentResource;
use App\Services\Core\CustomerPaymentService;
use Illuminate\Http\JsonResponse;

class CustomerPaymentController extends Controller
{
    public function __construct(
        private CustomerPaymentService $customerPaymentService
    ) {
    }

    /**
     * Add a payment for a customer bill.
     *
     *
     * @param CustomerPaymentRequest $request
     * @param int $billId
     * @return JsonResponse
     */
    public function addPayment(CustomerPaymentRequest $request, int $billId): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();

        // Add billId to the data
        $genericData->getData()->customerBillId = $billId;
        $genericData->syncDataArray();

        $payment = $this->customerPaymentService->addPayment($genericData);

        return ApiResponse::success(new CustomerPaymentResource($payment), 'Payment added successfully', 201);
    }

    /**
     * Delete a payment.
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function deletePayment(GenericRequest $request, int $id): JsonResponse
    {
        $data = $request->getGenericData();
        $this->customerPaymentService->deletePayment($id, $data->userData->account_id, $data->userData->id);

        return ApiResponse::success(null, 'Payment deleted successfully');
    }

    /**
     * Get all payments for a bill.
     *
     * @param GenericRequest $request
     * @param int $billId
     * @return JsonResponse
     */
    public function getBillPayments(GenericRequest $request, int $billId): JsonResponse
    {
        $data = $request->getGenericData();
        $payments = $this->customerPaymentService->getPaymentsForBill($billId, $data->userData->account_id);

        return ApiResponse::success(CustomerPaymentResource::collection($payments));
    }
}

