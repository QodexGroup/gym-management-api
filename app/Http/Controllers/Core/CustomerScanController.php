<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Requests\Core\CustomerScanRequest;
use App\Http\Resources\Core\CustomerScanResource;
use App\Repositories\Core\CustomerScanRepository;
use App\Services\Core\CustomerScanService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class CustomerScanController
{
    public function __construct(
        private CustomerScanRepository $customerScanRepository,
        private CustomerScanService $customerScanService,
    )
    {}

    /**
     * @return JsonResponse
     */
    public function getAllCustomerScans($customerId): JsonResponse
    {
        $customerScans = $this->customerScanRepository->getAllScans((int)$customerId);
        return ApiResponse::success(CustomerScanResource::collection($customerScans)->response()->getData(true));
    }

    /**
     * Get scans by customer ID and scan type
     *
     * @param int $customerId
     * @param string $scanType
     * @return JsonResponse
     */
    public function getScansByType($customerId, $scanType): JsonResponse
    {
        $scans = $this->customerScanRepository->getScansByType((int)$customerId, $scanType);
        return ApiResponse::success(CustomerScanResource::collection($scans));
    }

    /**
     * @param $customerId
     * @param CustomerScanRequest $request
     *
     * @return JsonResponse
     */
    public function createScan($customerId, CustomerScanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['customerId'] = (int)$customerId;
        $customerScan = $this->customerScanRepository->createScan($validated);
        return ApiResponse::success(new CustomerScanResource($customerScan->load('files')), 'Scan created successfully', 201);
    }

    /**
     * @param $id
     * @param CustomerScanRequest $request
     *
     * @return JsonResponse
     */
    public function updateCustomerScan($id, CustomerScanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $customerScan = $this->customerScanRepository->updateScan((int)$id, $validated);
        return ApiResponse::success(new CustomerScanResource($customerScan), 'Scan updated successfully');
    }

    /**
     * @param $id
     *
     * @return JsonResponse
     */
    public function deleteCustomerScan($id): JsonResponse
    {
        try {
            $fileUrls = $this->customerScanService->deleteScan((int)$id);

            return ApiResponse::success([
                'fileUrls' => $fileUrls, // Return file URLs for frontend to delete from Firebase
            ], 'Scan deleted successfully');
        } catch (\Throwable $th) {
            return ApiResponse::error('Failed to delete scan: ' . $th->getMessage(), 500);
        }
    }
}
