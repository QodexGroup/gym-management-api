<?php

namespace App\Services\Core;

use App\Constant\ClassSessionBookingStatusConstant;
use App\Helpers\GenericData;
use App\Models\Core\ClassSessionBooking;
use App\Repositories\Account\ClassScheduleSessionRepository;
use App\Repositories\Core\ClassSessionBookingRepository;
use Illuminate\Support\Facades\DB;

class ClassSessionBookingService
{
    public function __construct(
        private ClassSessionBookingRepository $bookingRepository,
        private ClassScheduleSessionRepository $sessionRepository
    ) {
    }

    /**
     * Book a class session for a client
     *
     * @param GenericData $genericData
     * @return void
     * @throws \Exception
     */
    public function bookSession(GenericData $genericData): void
    {
        $data = $genericData->getData();
        $sessionId = $data->sessionId;
        $customerId = $data->customerId;

        DB::transaction(function () use ($sessionId, $customerId, $genericData) {
            // Get session with class schedule to check capacity
            // Use repository method directly to get session by ID
            $session = $this->sessionRepository->getSessionById($sessionId, $genericData->userData->account_id);

            if (!$session) {
                throw new \Exception('Class session not found');
            }

            // Check capacity - count all bookings except 'cancelled'
            $bookingsCount = $this->bookingRepository->getBookingsCount($sessionId, $genericData);
            $capacity = $session->classSchedule->capacity ?? 0;

            if ($bookingsCount >= $capacity) {
                throw new \Exception('Class session is full');
            }

            // Check if customer already has a booking for this session
            $existing = $this->bookingRepository->checkExistingBooking($sessionId, $customerId, $genericData);
            if ($existing) {
                throw new \Exception('Customer already booked this session');
            }

            // Create booking
            $this->bookingRepository->createBooking($genericData);
        });
    }

    /**
     * Update attendance status for a specific booking
     *
     * @param int $bookingId
     * @param GenericData $genericData
     * @return ClassSessionBooking
     * @throws \Exception
     */
    public function updateAttendanceStatus(int $bookingId, GenericData $genericData): ClassSessionBooking
    {
        return DB::transaction(function () use ($bookingId, $genericData) {
            // Get booking to find session ID
            $booking = $this->bookingRepository->findBookingById($bookingId, $genericData);

            if (!$booking) {
                throw new \Exception('Booking not found');
            }

            // Update booking status
            $updated = $this->bookingRepository->updateBookingStatus($bookingId, $genericData);

            if (!$updated) {
                throw new \Exception('Failed to update booking status');
            }
            if($genericData->getData()->status == ClassSessionBookingStatusConstant::STATUS_ATTENDED) {

                $sessionId = $booking->class_schedule_session_id;
                $this->sessionRepository->updateAttendanceCountIncrementById($sessionId, $genericData->userData->account_id);
            }

            $booking->refresh();
            return $booking;
        });
    }

    /**
     * Mark all bookings for a session as attended
     *
     * @param int $sessionId
     * @param GenericData $genericData
     * @return int Number of updated records
     */
    public function markAllAsAttended(int $sessionId, GenericData $genericData): int
    {
        return DB::transaction(function () use ($sessionId, $genericData) {
            // Update all bookings status
            $updatedCount = $this->bookingRepository->updateAllBookingsStatus($sessionId, $genericData);

            // Sync attendance_count on related class schedule session
            $this->sessionRepository->updateAttendanceCountSession($sessionId, $genericData->userData->account_id, $updatedCount);

            return $updatedCount;
        });
    }

}
