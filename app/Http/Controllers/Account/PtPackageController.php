<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Http\Requests\Account\PtPackageRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\PtPackageResource;
use App\Repositories\Account\PtPackageRepository;
use App\Services\Account\AccountLimitService;
use Illuminate\Http\JsonResponse;

class PtPackageController
{
    public function __construct(
        private PtPackageRepository $ptPackageRepository,
        private AccountLimitService $accountLimitService
    ) {
    }

    /**
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getAllPtPackages(GenericRequest $request): JsonResponse
    {
        $data = $request->getGenericData();
        $packages = $this->ptPackageRepository->getAllPtPackages($data);
        return ApiResponse::success(PtPackageResource::collection($packages)->response()->getData(true));
    }

    /**
     * @param PtPackageRequest $request
     * @return JsonResponse
     */
    public function store(PtPackageRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $check = $this->accountLimitService->canCreate($genericData->userData->account_id, AccountLimitService::RESOURCE_PT_PACKAGES);
            if (!$check['allowed']) {
                return ApiResponse::error($check['message'] ?? 'Limit reached', 403);
            }
            $package = $this->ptPackageRepository->createPtPackage($genericData);
            return ApiResponse::success(new PtPackageResource($package), 'PT package created successfully', 201);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'limit') || str_contains($e->getMessage(), 'trial')) {
                return ApiResponse::error($e->getMessage(), 403);
            }
            throw $e;
        }
    }

    /**
     * @param PtPackageRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updatePtPackage(PtPackageRequest $request, int $id): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $package = $this->ptPackageRepository->updatePtPackage($id, $genericData);
        return ApiResponse::success(new PtPackageResource($package), 'PT package updated successfully');
    }

    /**
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function delete(GenericRequest $request, int $id): JsonResponse
    {
        $data = $request->getGenericData();
        $this->ptPackageRepository->deletePtPackage($id, $data->userData->account_id);
        return ApiResponse::success(null, 'PT package deleted successfully');
    }
}
