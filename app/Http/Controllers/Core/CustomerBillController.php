<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CustomerBillRequest;
use App\Http\Resources\Core\CustomerBillResource;
use App\Repositories\Core\CustomerBillRepository;
use App\Services\Core\CustomerBillService;
use Illuminate\Http\JsonResponse;

class CustomerBillController extends Controller
{
    public function __construct(
        private CustomerBillRepository $repository,
        private CustomerBillService $customerBillService
    ) {
    }

    /**
     * Get all bills for a specific customer
     *
     * @param int $customerId
     * @return JsonResponse
     */
    public function getCustomerBills(int $customerId): JsonResponse
    {
        $bills = $this->repository->getByCustomerId($customerId);
        return ApiResponse::success(CustomerBillResource::collection($bills)->response()->getData(true));
    }

    /**
     * Create a new bill
     *
     * @param CustomerBillRequest $request
     * @return JsonResponse
     */
    public function createBill(CustomerBillRequest $request): JsonResponse
    {
        $data = $request->validated();
        $bill = $this->customerBillService->create($data);
        return ApiResponse::success(new CustomerBillResource($bill), 'Bill created successfully', 201);
    }

    /**
     * Update a bill
     *
     * @param CustomerBillRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateBill(CustomerBillRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        $bill = $this->customerBillService->updateBill($id, $data);
        return ApiResponse::success(new CustomerBillResource($bill), 'Bill updated successfully');
    }

    /**
     * Delete a bill
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $this->customerBillService->deleteBill($id);
        return ApiResponse::success(null, 'Bill deleted successfully');
    }
}

