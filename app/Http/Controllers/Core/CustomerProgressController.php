<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Requests\Core\CustomerProgressRequest;
use App\Http\Resources\Core\CustomerProgressResource;
use App\Repositories\Core\CustomerProgressRepository;
use App\Services\Core\CustomerProgressService;
use Symfony\Component\HttpFoundation\JsonResponse;

class CustomerProgressController
{
    public function __construct(
        private CustomerProgressRepository $customerProgressRepository,
        private CustomerProgressService $customerProgressService,
    )
    {}

    public function getAllCustomerProgress($customerId): JsonResponse
    {
        $customerProgress = $this->customerProgressRepository->getAllProgress((int)$customerId);
        return ApiResponse::success(CustomerProgressResource::collection($customerProgress)->response()->getData(true));
    }

    /**
     * Get a specific progress record by ID
     *
     * @param int $customerId
     * @param int $id
     * @return JsonResponse
     */
    public function getProgressById($id): JsonResponse
    {
        $customerProgress = $this->customerProgressRepository->getProgressById((int)$id);
        return ApiResponse::success(new CustomerProgressResource($customerProgress));
    }

    /**
     * @param int $customerId
     * @param CustomerProgressRequest $request
     *
     * @return JsonResponse
     */
    public function createProgress($customerId, CustomerProgressRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['customerId'] = (int)$customerId;

        $customerProgress = $this->customerProgressRepository->createProgress($validated);

        return ApiResponse::success(new CustomerProgressResource($customerProgress->load('files')), 'Progress record created successfully', 201);
    }

    /**
     * @param int $customerId
     * @param int $id
     * @param CustomerProgressRequest $request
     *
     * @return JsonResponse
     */
    public function updateProgress($id, CustomerProgressRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $customerProgress = $this->customerProgressRepository->updateProgress((int)$id, $validated);

        return ApiResponse::success(new CustomerProgressResource($customerProgress), 'Progress record updated successfully');
    }

    /**
     * @param int $customerId
     * @param int $id
     *
     * @return JsonResponse
     */
    public function deleteProgress($id): JsonResponse
    {
        try {
            $fileUrls = $this->customerProgressService->deleteProgress((int)$id);

            return ApiResponse::success([
                'fileUrls' => $fileUrls, // Return file URLs for frontend to delete from Firebase
            ], 'Progress record deleted successfully');
        } catch (\Throwable $th) {
            return ApiResponse::error('Failed to delete progress: ' . $th->getMessage(), 500);
        }
    }
}
