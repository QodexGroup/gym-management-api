<?php

namespace App\Http\Controllers\Account;

use App\Helpers\ApiResponse;
use App\Http\Requests\Account\PtPackageRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Account\PtPackageResource;
use App\Repositories\Account\PtPackageRepository;
use Illuminate\Http\JsonResponse;

class PtPackageController
{
    public function __construct(
        private PtPackageRepository $ptPackageRepository
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
        return ApiResponse::success($packages);
    }

    /**
     * @param PtPackageRequest $request
     * @return JsonResponse
     */
    public function store(PtPackageRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $package = $this->ptPackageRepository->createPtPackage($genericData);
        return ApiResponse::success(new PtPackageResource($package), 'PT package created successfully', 201);
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
