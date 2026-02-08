<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\Core\CustomerScanRequest;
use App\Http\Resources\Core\CustomerScanResource;
use App\Repositories\Core\CustomerScanRepository;
use App\Services\Core\CustomerScanService;
use Symfony\Component\HttpFoundation\JsonResponse;

class CustomerScanController
{
    public function __construct(
        private CustomerScanRepository $customerScanRepository,
        private CustomerScanService $customerScanService,
    )
    {}

    /**
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getAllCustomerScans(GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericData();
        $customerScans = $this->customerScanRepository->getAllScans($genericData);
        return ApiResponse::success(CustomerScanResource::collection($customerScans)->response()->getData(true));
    }

    /**
     * Get scans by customer ID and scan type
     *
     * @param GenericRequest $request
     * @param string $scanType
     * @return JsonResponse
     */
    public function getScansByType(GenericRequest $request, $scanType): JsonResponse
    {
        $genericData = $request->getGenericData();
        $scans = $this->customerScanRepository->getScansByType($genericData, $scanType);
        return ApiResponse::success(CustomerScanResource::collection($scans));
    }

    /**
     * @param CustomerScanRequest $request
     *
     * @return JsonResponse
     */
    public function createScan(CustomerScanRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $customerScan = $this->customerScanRepository->createScan($genericData);
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
        $genericData = $request->getGenericDataWithValidated();
        $customerScan = $this->customerScanRepository->updateScan((int)$id, $genericData);
        return ApiResponse::success(new CustomerScanResource($customerScan), 'Scan updated successfully');
    }

    /**
     * @param GenericRequest $request
     * @param $id
     *
     * @return JsonResponse
     */
    public function deleteCustomerScan(GenericRequest $request, $id): JsonResponse
    {
        try {
            $genericData = $request->getGenericData();
            $fileUrls = $this->customerScanService->deleteScan((int)$id, $genericData->userData->account_id);

            return ApiResponse::success([
                'fileUrls' => $fileUrls, // Return file URLs for frontend to delete from Firebase
            ], 'Scan deleted successfully');
        } catch (\Throwable $th) {
            return ApiResponse::error('Failed to delete scan: ' . $th->getMessage(), 500);
        }
    }
}
