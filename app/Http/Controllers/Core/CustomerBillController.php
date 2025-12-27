<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CustomerBillRequest;
use App\Http\Requests\GenericRequest;
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
     * Get all bills for a specific customer with pagination, filtering, and sorting
     *
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getCustomerBills(GenericRequest $request): JsonResponse
    {
        $data = $request->getGenericData();
        $bills = $this->repository->getByCustomerId($data);
        return ApiResponse::success($bills);
    }

    /**
     * Create a new bill
     *
     * @param CustomerBillRequest $request
     * @return JsonResponse
     */
    public function createBill(CustomerBillRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $bill = $this->customerBillService->create($genericData);
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
        $genericData = $request->getGenericDataWithValidated();
        $bill = $this->customerBillService->updateBill($id, $genericData);
        return ApiResponse::success(new CustomerBillResource($bill), 'Bill updated successfully');
    }

    /**
     * Delete a bill
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function delete(GenericRequest $request, int $id): JsonResponse
    {
        $data = $request->getGenericData();
        $this->customerBillService->deleteBill($id, $data->userData->account_id);
        return ApiResponse::success(null, 'Bill deleted successfully');
    }
}

