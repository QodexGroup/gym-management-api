<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Requests\Core\CustomerPtPackageRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Core\CustomerPtPackageResource;
use App\Repositories\Core\CustomerPtPackageRepository;
use App\Services\Core\CustomerService;
use App\Services\Core\CustomerPtPackageService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class CustomerPtPackageController
{
    public function __construct(
         private CustomerPtPackageRepository $customerPtPackageRepository,
         private CustomerPtPackageService $customerPtPackageService
    ) {
    }

    /**
     * @param GenericRequest $request
     * @param int $customerId
     *
     * @return JsonResponse
     */
    public function getPtPackages(GenericRequest $request, int $customerId): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $ptPackages = $this->customerPtPackageRepository->getPtPackages($customerId, $genericData);
        return ApiResponse::success(CustomerPtPackageResource::collection($ptPackages));
    }

     /**
     * Remove/cancel PT package for a customer
     *
     * @param GenericRequest $request
     * @param int $ptPackageId PT Package ID
     * @return JsonResponse
     */
    public function removePtPackage(GenericRequest $request, int $ptPackageId): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $removed = $this->customerPtPackageService->removePtPackage($ptPackageId, $genericData);
        if (!$removed) {
            return ApiResponse::error('Failed to remove PT package', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return ApiResponse::success($removed, 'PT Package removed successfully');
    }
}
