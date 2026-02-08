<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Common\FilterDateRequest;
use App\Http\Requests\Core\PtBookingRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Core\PtBookingResource;
use App\Repositories\Core\PtBookingRepository;
use App\Services\Core\PtBookingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PtBookingController extends Controller
{
    public function __construct(
        private PtBookingService $ptBookingService,
        private PtBookingRepository $ptBookingRepository
    ) {
    }

    /**
     * Get booking sessions for calendar view (by date range)
     *
     * @param FilterDateRequest $request
     * @return JsonResponse
     */
    public function getPtBookings(FilterDateRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $bookings = $this->ptBookingRepository->getPtBookingsByDateRange($genericData);
            return ApiResponse::success(PtBookingResource::collection($bookings)->response()->getData(true));
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get PT bookings for a specific coach by date range
     *
     * @param int $coachId
     * @param FilterDateRequest $request
     * @return JsonResponse
     */
    public function getCoachPtBookings(int $coachId, FilterDateRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $bookings = $this->ptBookingRepository->getCoachPtBookings($genericData, $coachId);
            return ApiResponse::success(PtBookingResource::collection($bookings)->response()->getData(true));
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get PT bookings for a specific class schedule session
     *
     * @param int $sessionId
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getPtBookingsBySession(int $sessionId, GenericRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericData();
            $bookings = $this->ptBookingRepository->getPtBookingsByClassScheduleSession($sessionId, $genericData->userData->account_id);
            return ApiResponse::success(PtBookingResource::collection($bookings)->response()->getData(true));
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param PtBookingRequest $request
     *
     * @return JsonResponse
     */
    public function create(PtBookingRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $ptBooking = $this->ptBookingService->createPtBooking($genericData);
        if (!$ptBooking) {
            return ApiResponse::error('Failed to create PT booking', Response::HTTP_BAD_REQUEST);
        }
        return ApiResponse::success(new PtBookingResource($ptBooking), 'PT booking created successfully');
    }

    /**
     * @param PtBookingRequest $request
     *
     * @return JsonResponse
     */
    public function update(int $id, PtBookingRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $ptBooking = $this->ptBookingService->updatePtBooking($id, $genericData);
        if (!$ptBooking) {
            return ApiResponse::error('Failed to update PT booking', Response::HTTP_BAD_REQUEST);
        }
        return ApiResponse::success(new PtBookingResource($ptBooking), 'PT booking updated successfully');
    }

    /**
     * @param int $id
     *
     * @return JsonResponse
     */
    public function markAsCancelled(int $id, GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericData();
        $ptBooking = $this->ptBookingRepository->markAsCancelled($id, $genericData);
        if (!$ptBooking) {
            return ApiResponse::error('Failed to mark PT booking as cancelled', Response::HTTP_BAD_REQUEST);
        }
        return ApiResponse::success(new PtBookingResource($ptBooking), 'PT booking marked as cancelled successfully');
    }

    /**
     * @param int $id
     *
     * @return JsonResponse
     */
    public function markAsAttended(int $id, GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericData();
        $ptBooking = $this->ptBookingService->markAsAttended($id, $genericData);
        if (!$ptBooking) {
            return ApiResponse::error('Failed to mark PT booking as attended', Response::HTTP_BAD_REQUEST);
        }
        return ApiResponse::success(new PtBookingResource($ptBooking), 'PT booking marked as attended successfully');
    }

    /**
     * @param int $id
     *
     * @return JsonResponse
     */
    public function markAsNoShow(int $id, GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericData();
        $ptBooking = $this->ptBookingRepository->markAsNoShow($id, $genericData);
        if (!$ptBooking) {
            return ApiResponse::error('Failed to mark PT booking as no show', Response::HTTP_BAD_REQUEST);
        }
        return ApiResponse::success(new PtBookingResource($ptBooking), 'PT booking marked as no show successfully');
    }

    /**
     * Get upcoming PT bookings for a customer
     *
     * @param int $customerId
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getCustomerUpcomingPtBookings(int $customerId, GenericRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericData();
            $bookings = $this->ptBookingRepository->getCustomerUpcomingPtBookings($genericData, $customerId);
            return ApiResponse::success(PtBookingResource::collection($bookings));
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get paginated PT booking history for a customer
     *
     * @param int $customerId
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getCustomerPtBookingHistory(int $customerId, GenericRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericData();
            $bookings = $this->ptBookingRepository->getCustomerPtBookingHistory($genericData, $customerId);
            return ApiResponse::success(PtBookingResource::collection($bookings)->response()->getData(true));
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
