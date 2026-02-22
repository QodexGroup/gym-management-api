<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CustomerRequest;
use App\Http\Requests\Core\CustomerMembershipRequest;
use App\Http\Requests\Core\CustomerPtPackageRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Core\CustomerResource;
use App\Http\Resources\Core\CustomerMembershipResource;
use App\Http\Resources\Core\CustomerPtPackageResource;
use App\Repositories\Core\CustomerRepository;
use App\Repositories\Account\MembershipPlanRepository;
use App\Services\Core\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerRepository $repository,
        private CustomerService $customerService
    ) {
    }

    /**
     * Get all customers by account_id with pagination, filtering, and sorting
     *
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getCustomers(GenericRequest $request): JsonResponse
    {
        $data = $request->getGenericData();
        $customers = $this->repository->getAll($data);
        return ApiResponse::success(CustomerResource::collection($customers)->response()->getData(true));
    }

    /**
     * Get a customer by id
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function getCustomer(GenericRequest $request, int $id): JsonResponse
    {
        $data = $request->getGenericData();
        $customer = $this->repository->findCustomerById($id, $data->userData->account_id);
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
        try {
            $genericData = $request->getGenericDataWithValidated();
            $customer = $this->customerService->create($genericData);
            return ApiResponse::success(new CustomerResource($customer), 'Customer created successfully', 201);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'limit') || str_contains($e->getMessage(), 'trial')) {
                return ApiResponse::error($e->getMessage(), 403);
            }
            throw $e;
        }
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
        $genericData = $request->getGenericDataWithValidated();
        $customer = $this->customerService->update($id, $genericData);
        return ApiResponse::success(new CustomerResource($customer), 'Customer updated successfully');
    }

    /**
     * Delete a customer (soft delete)
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function delete(GenericRequest $request, int $id): JsonResponse
    {
        $data = $request->getGenericData();
        $this->repository->delete($id, $data->userData->account_id);
        return ApiResponse::success(null, 'Customer deleted successfully');
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
            $genericData = $request->getGenericDataWithValidated();
            $membership = $this->customerService->createOrUpdateMembership($id, $genericData);

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

    /**
     * Create pt package for a customer
     *
     * @param CustomerPtPackageRequest $request
     * @param int $customerId Customer ID
     * @return JsonResponse
     */
    public function createPtPackage(CustomerPtPackageRequest $request, int $customerId): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $ptPackage = $this->customerService->createPtPackage($customerId, $genericData);
        if (!$ptPackage) {
            return ApiResponse::error('Failed to create PT package', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return ApiResponse::success(new CustomerPtPackageResource($ptPackage), 'PT Package created successfully');
    }
}

