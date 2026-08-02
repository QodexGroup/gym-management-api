<?php

namespace App\Modules\Imports\Controller;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Imports\Request\ImportExecuteRequest;
use App\Modules\Imports\Request\ImportUploadRequest;
use App\Http\Requests\GenericRequest;
use App\Modules\Imports\Resource\ImportJobResource;
use App\Modules\Imports\Resource\ImportResultResource;
use App\Modules\Imports\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(private ImportService $importService)
    {
    }

    /**
     * List the available import types.
     *
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function types(GenericRequest $request): JsonResponse
    {
        return ApiResponse::success($this->importService->getTypes());
    }

    /**
     * Get field definitions and options for an import type.
     *
     * @param GenericRequest $request
     * @param string $type
     * @return JsonResponse
     */
    public function fields(GenericRequest $request, string $type): JsonResponse
    {
        try {
            return ApiResponse::success($this->importService->getFieldsAndOptions($type));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Upload and parse a file, creating a pending import job.
     *
     * @param ImportUploadRequest $request
     * @return JsonResponse
     */
    public function upload(ImportUploadRequest $request): JsonResponse
    {
        try {
            $result = $this->importService->upload($request->getGenericDataWithValidated());
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($result, 'File uploaded successfully');
    }

    /**
     * Save the column mapping and dispatch the async import.
     *
     * @param ImportExecuteRequest $request
     * @return JsonResponse
     */
    public function execute(ImportExecuteRequest $request): JsonResponse
    {
        $job = $this->importService->execute($request->getGenericDataWithValidated());

        return ApiResponse::success(new ImportJobResource($job), 'Import started');
    }

    /**
     * Get the status/progress of an import job.
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function status(GenericRequest $request, int $id): JsonResponse
    {
        $job = $this->importService->getStatus($id, $request->getGenericData());
        if (!$job) {
            return ApiResponse::error('Import job not found', 404);
        }

        return ApiResponse::success(new ImportJobResource($job));
    }

    /**
     * Paginated import history for the account.
     *
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function history(GenericRequest $request): JsonResponse
    {
        $history = $this->importService->getHistory($request->getGenericData());

        return ApiResponse::success(
            ImportJobResource::collection($history)->response()->getData(true)
        );
    }

    /**
     * Get failed row results for an import job.
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function failed(GenericRequest $request, int $id): JsonResponse
    {
        $results = $this->importService->getFailedResults($id, $request->getGenericData());

        return ApiResponse::success(ImportResultResource::collection($results));
    }

    /**
     * Download the generated error file (failed/skipped rows) for a job.
     *
     * @param GenericRequest $request
     * @param int $id
     * @return StreamedResponse|JsonResponse
     */
    public function downloadResult(GenericRequest $request, int $id)
    {
        $download = $this->importService->downloadResultFile($id, $request->getGenericData());
        if (!$download) {
            return ApiResponse::error('No result file available for this import', 404);
        }

        return $download;
    }
}
