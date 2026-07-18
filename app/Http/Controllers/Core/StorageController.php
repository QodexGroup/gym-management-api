<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Core\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorageController extends Controller
{
    /**
     * @param StorageService $storageService
     */
    public function __construct(
        private StorageService $storageService,
    ) {}

    /**
     * Issue a short-lived presigned R2 upload URL — but only after checking the
     * account's storage quota. Over-quota requests throw QuotaExceededException
     * (rendered as 403), so the upload never runs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPresignedUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string|max:500',
            'content_type' => 'required|string|in:image/jpeg,image/jpg,image/png,image/webp,application/pdf',
            'content_length' => 'required|integer|min:1',
        ]);

        $accountId = (int) $request->attributes->get('user')->account_id;

        $result = $this->storageService->createPresignedUpload(
            $accountId,
            $validated['path'],
            $validated['content_type'],
            (int) $validated['content_length'],
        );

        return ApiResponse::success($result);
    }

    /**
     * Current storage usage/limit for the authenticated account.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function usage(Request $request): JsonResponse
    {
        $accountId = (int) $request->attributes->get('user')->account_id;

        return ApiResponse::success($this->storageService->getUsage($accountId));
    }
}
