<?php

namespace App\Http\Controllers\Common;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Common\QrCheckinRequest;
use App\Http\Requests\Common\WalkinRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Common\WalkinCustomerResource;
use App\Http\Resources\Common\WalkinResource;
use App\Repositories\Common\WalkinRepository;
use App\Services\Common\WalkinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WalkinController extends Controller
{
    protected WalkinRepository $walkinRepository;
    protected WalkinService $walkinService;

    public function __construct(WalkinRepository $walkinRepository, WalkinService $walkinService)
    {
        $this->walkinRepository = $walkinRepository;
        $this->walkinService = $walkinService;
    }


    /**
     * @param WalkinRequest $request
     *
     * @return JsonResponse
     */
    public function createWalkin(WalkinRequest $request): JsonResponse
    {
        try {

            $genericData =  $request->getGenericDataWithValidated();
            $walkin = $this->walkinRepository->createWalkin($genericData);
            return ApiResponse::success(new WalkinResource($walkin));

        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse
     */
    public function getWalkin(GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericData();
        $walkin = $this->walkinRepository->getWalkin($genericData);
        if (!$walkin) {
            return ApiResponse::error('Walkin not found', 404);
        }
        return ApiResponse::success(new WalkinResource($walkin));
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse
     */
    public function getPaginatedWalkinCustomers(int $walkinId, GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericData();
        $walkinCustomers = $this->walkinRepository->getPaginatedWalkinCustomers($walkinId, $genericData);
        return ApiResponse::success(WalkinCustomerResource::collection($walkinCustomers)->response()->getData(true));
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse
     */
    public function getPaginatedWalkinByCustomer(int $customerId, GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericData();
        $walkinCustomers = $this->walkinRepository->getPaginatedWalkinByCustomer($customerId, $genericData);
        return ApiResponse::success(WalkinCustomerResource::collection($walkinCustomers)->response()->getData(true));
    }

    /**
     * @param WalkinRequest $request
     * @param int $walkinId
     *
     * @return JsonResponse
     */
    public function createWalkinCustomer(int $walkinId, WalkinRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $walkinCustomer = $this->walkinService->createWalkinCustomer($walkinId, $genericData);
        return ApiResponse::success(new WalkinCustomerResource($walkinCustomer));
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse
     */
    public function checkOutWalkinCustomer(int $id, GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $walkinCustomer = $this->walkinRepository->checkOutWalkinCustomer($id, $genericData);
        return ApiResponse::success(new WalkinCustomerResource($walkinCustomer));
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse
     */
    public function cancelWalkinCustomer(int $id, GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $walkinCustomer = $this->walkinRepository->cancelWalkinCustomer($id, $genericData);
        return ApiResponse::success(new WalkinCustomerResource($walkinCustomer));
    }

    /**
     * QR Code Check-in (convenience endpoint for kiosk)
     * Gets customer by UUID, gets or creates today's walk-in, and checks in the customer
     *
     * @param QrCheckinRequest $request
     * @return JsonResponse
     */
    public function qrCheckIn(QrCheckinRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $walkinCustomer = $this->walkinService->qrCheckIn($genericData);
            return ApiResponse::success(new WalkinCustomerResource($walkinCustomer), 'Customer checked in successfully');
        } catch (\Exception $e) {
            Log::error('QR check-in error: ' . $e->getMessage());
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * QR Code Check-out (convenience endpoint for kiosk)
     * Gets customer by UUID, finds today's walk-in, and checks out the customer
     *
     * @param QrCheckinRequest $request
     * @return JsonResponse
     */
    public function qrCheckOut(QrCheckinRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $walkinCustomer = $this->walkinService->qrCheckOut($genericData);
            return ApiResponse::success(new WalkinCustomerResource($walkinCustomer), 'Customer checked out successfully');
        } catch (\Exception $e) {
            Log::error('QR check-out error: ' . $e->getMessage());
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
