<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CustomerPaymentRequest;
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
        $validated = $request->validated();
        $validated['customerBillId'] = $billId;

        $payment = $this->customerPaymentService->addPayment($validated);

        return ApiResponse::success(new CustomerPaymentResource($payment), 'Payment added successfully', 201);
    }

    /**
     * Delete a payment.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deletePayment(int $id): JsonResponse
    {
        $this->customerPaymentService->deletePayment($id);

        return ApiResponse::success(null, 'Payment deleted successfully');
    }

    /**
     * Get all payments for a bill.
     *
     * @param int $billId
     * @return JsonResponse
     */
    public function getBillPayments(int $billId): JsonResponse
    {
        $payments = $this->customerPaymentService->getPaymentsForBill($billId);

        return ApiResponse::success(CustomerPaymentResource::collection($payments));
    }
}

