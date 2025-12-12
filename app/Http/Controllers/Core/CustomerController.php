<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CustomerRequest;
use App\Http\Resources\Core\CustomerResource;
use App\Http\Resources\Core\TrainerResource;
use App\Repositories\Core\CustomerRepository;
use App\Services\Core\CustomerService;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerRepository $repository,
        private CustomerService $customerService
    ) {
    }

    /**
     * Get all customers by account_id with pagination (50 per page)
     *
     * @return JsonResponse
     */
    public function getCustomers(): JsonResponse
    {
        $customers = $this->repository->getAll();
        return ApiResponse::success(CustomerResource::collection($customers)->response()->getData(true));
    }

    /**
     * Get a customer by id
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getCustomer(int $id): JsonResponse
    {
        $customer = $this->repository->getById($id);
        return ApiResponse::success(new CustomerResource($customer));
    }

    /**
     * Create a new customer
     *
     * @param CustomerRequest $request
     * @return JsonResponse
     */
    public function store(CustomerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $customer = $this->customerService->create($data);
        return ApiResponse::success(new CustomerResource($customer), 'Customer created successfully', 201);
    }

    /**
     * Update a customer
     *
     * @param CustomerRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateCustomer(CustomerRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        $customer = $this->customerService->update($id, $data);
        return ApiResponse::success(new CustomerResource($customer), 'Customer updated successfully');
    }

    /**
     * Delete a customer (soft delete)
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $this->repository->delete($id);
        return ApiResponse::success(null, 'Customer deleted successfully');
    }

    /**
     * Get all trainers (users) for selection
     *
     * @return JsonResponse
     */
    public function getTrainers(): JsonResponse
    {
        // For now, return dummy trainer (id: 1, name: Jomilen Dela Torre)
        // In the future, this can be filtered by role or other criteria
        $dummyTrainer = new \App\Models\User([
            'id' => 1,
            'name' => 'Jomilen Dela Torre',
            'email' => 'jomilen@example.com',
        ]);

        return ApiResponse::success([new TrainerResource($dummyTrainer)]);
    }
}

