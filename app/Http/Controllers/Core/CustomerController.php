<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CustomerRequest;
use App\Http\Requests\Core\CustomerMembershipRequest;
use App\Http\Resources\Core\CustomerResource;
use App\Http\Resources\Core\CustomerMembershipResource;
use App\Http\Resources\Core\TrainerResource;
use App\Models\User;
use App\Repositories\Core\CustomerRepository;
use App\Repositories\Account\MembershipPlanRepository;
use App\Services\Core\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerRepository $repository,
        private CustomerService $customerService,
        private MembershipPlanRepository $membershipPlanRepository
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
        $dummyTrainer = new User();
        $dummyTrainer->id = 1;
        $dummyTrainer->name = 'Jomilen Dela Torre';
        $dummyTrainer->email = 'jomilen@example.com';

    return ApiResponse::success([new TrainerResource($dummyTrainer)]);

        return ApiResponse::success([new TrainerResource($dummyTrainer)]);
    }

    /**
     * Create or update customer membership
     *
     * @param CustomerMembershipRequest $request
     * @param int $id Customer ID
     * @return JsonResponse
     */
    public function createOrUpdateMembership(CustomerMembershipRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();
            $membership = $this->customerService->createOrUpdateMembership($id, $data);

            return ApiResponse::success([
                'membership' => new CustomerMembershipResource($membership),
            ], 'Membership created/updated successfully');
        } catch (\Throwable $th) {
            Log::error('Error creating/updating customer membership', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            return ApiResponse::error('Failed to create/update membership: ' . $th->getMessage(), 500);
        }
    }
}

