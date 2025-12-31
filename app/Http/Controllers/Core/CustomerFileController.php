<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\Core\CustomerFileRequest;
use App\Http\Resources\Core\CustomerFileResource;
use App\Models\Core\CustomerProgress;
use App\Models\Core\CustomerScans;
use App\Services\Core\FileService;
use App\Repositories\Core\CustomerFileRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class CustomerFileController
{
    public function __construct(
        private FileService $fileService,
        private CustomerFileRepository $customerFileRepository,
    )
    {}

    /**
     * Create a file record for a progress entry
     * fileableType is automatically set to CustomerProgress
     *
     * @param int $progressId
     * @param CustomerFileRequest $request
     * @return JsonResponse
     */
    public function createProgressFile($progressId, CustomerFileRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $file = $this->fileService->createFile(CustomerProgress::class, (int)$progressId, $genericData);

        return ApiResponse::success(new CustomerFileResource($file), 'File saved successfully', 201);
    }

    /**
     * Create a file record for a scan entry
     * fileableType is automatically set to CustomerScans
     *
     * @param int $scanId
     * @param CustomerFileRequest $request
     * @return JsonResponse
     */
    public function createScanFile($scanId, CustomerFileRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $file = $this->fileService->createFile(CustomerScans::class, (int)$scanId, $genericData);

        return ApiResponse::success(new CustomerFileResource($file), 'File saved successfully', 201);
    }

    /**
     * Delete a file record
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function deleteFile(GenericRequest $request, $id): JsonResponse
    {
        $genericData = $request->getGenericData();
        $result = $this->fileService->deleteFile((int)$id, $genericData->userData->account_id);

        if (!$result) {
            return ApiResponse::error('File not found', 404);
        }

        return ApiResponse::success($result, 'File deleted successfully');
    }

    /**
     * Get all files for a customer
     *
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getFilesByCustomerId(GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericData();
        $files = $this->customerFileRepository->getFilesByCustomerId($genericData);
        return ApiResponse::success(CustomerFileResource::collection($files));
    }
}

