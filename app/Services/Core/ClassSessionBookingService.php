<?php

namespace App\Services\Core;

use App\Constant\ClassSessionBookingStatusConstant;
use App\Helpers\GenericData;
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

}
