<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Requests\GenericRequest;
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

    public function getAllCustomerProgress(GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericData();
        $customerProgress = $this->customerProgressRepository->getAllProgress($genericData);
        return ApiResponse::success($customerProgress);
    }

    /**
     * Get a specific progress record by ID
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function getProgressById(GenericRequest $request, $id): JsonResponse
    {
        $genericData = $request->getGenericData();
        $customerProgress = $this->customerProgressRepository->getProgressById((int)$id, $genericData->userData->account_id);
        return ApiResponse::success(new CustomerProgressResource($customerProgress));
    }

    /**
     * @param CustomerProgressRequest $request
     *
     * @return JsonResponse
     */
    public function createProgress(CustomerProgressRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $customerProgress = $this->customerProgressRepository->createProgress($genericData);

        return ApiResponse::success(new CustomerProgressResource($customerProgress->load('files')), 'Progress record created successfully', 201);
    }

    /**
     * @param int $id
     * @param CustomerProgressRequest $request
     *
     * @return JsonResponse
     */
    public function updateProgress($id, CustomerProgressRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $customerProgress = $this->customerProgressRepository->updateProgress((int)$id, $genericData);

        return ApiResponse::success(new CustomerProgressResource($customerProgress), 'Progress record updated successfully');
    }

    /**
     * @param GenericRequest $request
     * @param int $id
     *
     * @return JsonResponse
     */
    public function deleteProgress(GenericRequest $request, $id): JsonResponse
    {
        try {
            $genericData = $request->getGenericData();
            $fileUrls = $this->customerProgressService->deleteProgress((int)$id, $genericData->userData->account_id);

            return ApiResponse::success([
                'fileUrls' => $fileUrls, // Return file URLs for frontend to delete from Firebase
            ], 'Progress record deleted successfully');
        } catch (\Throwable $th) {
            return ApiResponse::error('Failed to delete progress: ' . $th->getMessage(), 500);
        }
    }
}
