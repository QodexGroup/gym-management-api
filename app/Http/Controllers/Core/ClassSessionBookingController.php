<?php

namespace App\Http\Controllers\Core;

use App\Constant\ClassSessionBookingStatusConstant;
use App\Helpers\ApiResponse;
use App\Http\Requests\Common\FilterDateRequest;
use App\Http\Requests\Core\ClassSessionBookingRequest;
use App\Http\Requests\GenericRequest;
use App\Http\Resources\Core\ClassSessionBookingResource;
use App\Repositories\Account\ClassScheduleSessionRepository;
use App\Repositories\Core\ClassSessionBookingRepository;
use App\Services\Account\GroupClassSessionAuthorizationService;
use App\Services\Core\ClassSessionBookingService;
use Illuminate\Http\JsonResponse;

class ClassSessionBookingController
{
    public function __construct(
        private ClassSessionBookingService $bookingService,
        private ClassSessionBookingRepository $bookingRepository,
        private ClassScheduleSessionRepository $sessionRepository,
        private GroupClassSessionAuthorizationService $groupClassSessionAuthorization
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
            if (ClassSessionBookingService::isDeactivatedClientCannotJoinMessage($e->getMessage())) {
                return ApiResponse::error($e->getMessage(), 403);
            }

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
            $existing = $this->bookingRepository->findBookingById($bookingId, $genericData);

            if (!$existing || !$existing->classScheduleSession) {
                return ApiResponse::error('Booking not found', 404);
            }

            $this->groupClassSessionAuthorization->assertMayManageGroupClassSession(
                $genericData->userData,
                $existing->classScheduleSession
            );

            $booking = $this->bookingService->updateAttendanceStatus($bookingId, $genericData);

            return ApiResponse::success(new ClassSessionBookingResource($booking), 'Attendance status updated successfully');
        } catch (\Exception $e) {
            if (GroupClassSessionAuthorizationService::isForbiddenAuthorizationMessage($e->getMessage())) {
                return ApiResponse::error($e->getMessage(), 403);
            }

            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Update a booking (customer, session, notes)
     *
     * @param ClassSessionBookingRequest $request
     * @param int $bookingId
     * @return JsonResponse
     */
    public function updateBooking(ClassSessionBookingRequest $request, int $bookingId): JsonResponse
    {
        try {
            $genericData = $request->getGenericDataWithValidated();
            $existing = $this->bookingRepository->findBookingById($bookingId, $genericData);

            if (!$existing || !$existing->classScheduleSession) {
                return ApiResponse::error('Booking not found', 404);
            }

            $this->groupClassSessionAuthorization->assertMayManageGroupClassSession(
                $genericData->userData,
                $existing->classScheduleSession
            );

            $dto = $genericData->getData();
            $accountId = (int) $genericData->userData->account_id;

            if (isset($dto->sessionId) && (int) $dto->sessionId !== (int) $existing->class_schedule_session_id) {
                $targetSession = $this->sessionRepository->getSessionById((int) $dto->sessionId, $accountId);
                $this->groupClassSessionAuthorization->assertMayManageGroupClassSession(
                    $genericData->userData,
                    $targetSession
                );
            }

            if (isset($dto->customerId) && (int) $dto->customerId !== (int) $existing->customer_id) {
                $this->bookingService->ensureCustomerMayJoinGroupClassSession(
                    (int) $dto->customerId,
                    $accountId
                );
            }

            $booking = $this->bookingRepository->updateBooking($bookingId, $genericData);

            if (!$booking) {
                return ApiResponse::error('Booking not found', 404);
            }

            return ApiResponse::success(new ClassSessionBookingResource($booking), 'Booking updated successfully');
        } catch (\Exception $e) {
            if (GroupClassSessionAuthorizationService::isForbiddenAuthorizationMessage($e->getMessage())) {
                return ApiResponse::error($e->getMessage(), 403);
            }

            if (ClassSessionBookingService::isDeactivatedClientCannotJoinMessage($e->getMessage())) {
                return ApiResponse::error($e->getMessage(), 403);
            }

            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Get a specific booking by ID
     *
     * @param GenericRequest $request
     * @param int $bookingId
     * @return JsonResponse
     */
    public function getBookingById(GenericRequest $request, int $bookingId): JsonResponse
    {
        try {
            $genericData = $request->getGenericData();
            $booking = $this->bookingRepository->findBookingById($bookingId, $genericData);

            if (!$booking) {
                return ApiResponse::error('Booking not found', 404);
            }

            return ApiResponse::success(new ClassSessionBookingResource($booking));
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
            $accountId = (int) $genericData->userData->account_id;
            $session = $this->sessionRepository->getSessionById($sessionId, $accountId);
            $this->groupClassSessionAuthorization->assertMayManageGroupClassSession(
                $genericData->userData,
                $session
            );

            $updatedCount = $this->bookingService->markAllAsAttended($sessionId, $genericData);
            return ApiResponse::success($updatedCount, 'All bookings marked as attended');
        } catch (\Exception $e) {
            if (GroupClassSessionAuthorizationService::isForbiddenAuthorizationMessage($e->getMessage())) {
                return ApiResponse::error($e->getMessage(), 403);
            }

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

    /**
     * Get customer's class session booking history
     *
     * @param int $customerId
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getCustomerClassSessionBookingHistory(int $customerId, GenericRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericData();
            $bookings = $this->bookingRepository->getCustomerClassSessionBookingHistory($genericData, $customerId);
            return ApiResponse::success(ClassSessionBookingResource::collection($bookings)->response()->getData(true));
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
