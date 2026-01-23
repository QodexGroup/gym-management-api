<?php

namespace App\Http\Controllers\Core;

use App\Constant\ClassSessionBookingStatusConstant;
use App\Helpers\ApiResponse;
use App\Http\Requests\Common\FilterDateRequest;
use App\Http\Requests\Core\ClassSessionBookingRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Core\ClassSessionBookingResource;
use App\Repositories\Core\ClassSessionBookingRepository;
use App\Services\Core\ClassSessionBookingService;
use Illuminate\Http\JsonResponse;

class ClassSessionBookingController
{
    public function __construct(
        private ClassSessionBookingService $bookingService,
        private ClassSessionBookingRepository $bookingRepository
    ) {
    }

    /**
     * Book a class session for a client
     *
     * @param ClassSessionBookingRequest $request
     * @return JsonResponse
     */
    public function bookSession(ClassSessionBookingRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();

            $this->bookingService->bookSession($genericData);
            return ApiResponse::success(null, 'Class session booked successfully');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Get all bookings for a specific session
     *
     * @param GenericRequest $request
     * @param int $sessionId
     * @return JsonResponse
     */
    public function getBookingsBySession(GenericRequest $request, int $sessionId): JsonResponse
    {
        try {
            $data = $request->getGenericData();
            $bookings = $this->bookingRepository->getBookingsBySessionId($sessionId, $data);
            return ApiResponse::success(ClassSessionBookingResource::collection($bookings));
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Update attendance status for a specific booking
     *
     * @param ClassSessionBookingRequest $request
     * @param int $bookingId
     * @return JsonResponse
     */
    public function updateAttendanceStatus(ClassSessionBookingRequest $request, int $bookingId): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $updated = $this->bookingRepository->updateBookingStatus($bookingId, $genericData);
            if (!$updated) {
                return ApiResponse::error('Booking not found or update failed', 404);
            }

            return ApiResponse::success(null, 'Attendance status updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Mark all bookings for a session as attended
     *
     * @param GenericRequest $request
     * @param int $sessionId
     * @return JsonResponse
     */
    public function markAllAsAttended(GenericRequest $request, int $sessionId): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $this->bookingRepository->updateAllBookingsStatus($sessionId, $genericData);
            return ApiResponse::success(null, 'All bookings marked as attended');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Get booking sessions for calendar view (by date range)
     *
     * @param FilterDateRequest $request
     * @return JsonResponse
     */
    public function getBookingSessions(FilterDateRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $bookings = $this->bookingRepository->getBookingSessionsByDateRange($genericData);
            return ApiResponse::success(ClassSessionBookingResource::collection($bookings));
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
