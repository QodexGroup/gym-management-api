<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\PtCategoryResource;
use App\Repositories\Account\PtCategoryRepository;
use Illuminate\Http\JsonResponse;

class PtCategoryController
{
    public function __construct(
        private PtCategoryRepository $ptCategoryRepository
    ) {
    }

    /**
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getAllPtCategories(GenericRequest $request): JsonResponse
    {
        $data = $request->getGenericData();
        $categories = $this->ptCategoryRepository->getAllPtCategories($data);
        return ApiResponse::success($categories);
    }
}
