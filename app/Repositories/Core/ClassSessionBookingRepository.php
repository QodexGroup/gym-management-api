<?php

namespace App\Repositories\Core;

use App\Constant\ClassSessionBookingStatusConstant;
use App\Helpers\GenericData;
use App\Models\Core\ClassSessionBooking;
use Illuminate\Database\Eloquent\Collection;

class ClassSessionBookingRepository
{
    /**
     * Get all bookings for a specific class session
     *
     * @param int $sessionId
     * @param GenericData $genericData
     * @return Collection
     */
    public function getBookingsBySessionId(int $sessionId, GenericData $genericData): Collection
    {
        return ClassSessionBooking::where('account_id', $genericData->userData->account_id)
            ->where('class_schedule_session_id', $sessionId)
            ->where('status', '!=', ClassSessionBookingStatusConstant::STATUS_CANCELLED)
            ->with(['customer'])
            ->get();
    }

    /**
     * Create a new booking
     *
     * @param array $data
     * @return ClassSessionBooking
     */
    public function createBooking(GenericData $genericData): ClassSessionBooking
    {
        return ClassSessionBooking::create([
            'account_id' => $genericData->userData->account_id,
            'class_schedule_session_id' => $genericData->getData()->sessionId,
            'customer_id' => $genericData->getData()->customerId,
            'status' => ClassSessionBookingStatusConstant::STATUS_BOOKED,
            'notes' => $genericData->getData()->notes,
            'created_by' => $genericData->userData->id,
            'updated_by' => $genericData->userData->id,
        ]);
    }

    /**
     * Update the status of a specific booking
     *
     * @param int $id
     * @param GenericData $genericData
     * @return bool
     */
    public function updateBookingStatus(int $id, GenericData $genericData): bool
    {
        $data = $genericData->getData();
        return ClassSessionBooking::where('id', $id)
            ->where('account_id', $genericData->userData->account_id)
            ->update([
                'status' => $data->status,
                'updated_by' => $genericData->userData->id,
            ]);
    }

    /**
     * Update all bookings for a session to the same status
     *
     * @param int $sessionId
     * @param GenericData $genericData
     * @return int Number of updated records
     */
    public function updateAllBookingsStatus(int $sessionId, GenericData $genericData): int
    {
        return ClassSessionBooking::where('class_schedule_session_id', $sessionId)
            ->where('account_id', $genericData->userData->account_id)
            ->where('status', '=', ClassSessionBookingStatusConstant::STATUS_BOOKED)
            ->update([
                'status' => ClassSessionBookingStatusConstant::STATUS_ATTENDED,
                'updated_by' => $genericData->userData->id,
            ]);
    }

    /**
     * Check if a customer already has a booking for a session
     *
     * @param int $sessionId
     * @param int $customerId
     * @param GenericData $genericData
     * @return ClassSessionBooking|null
     */
    public function checkExistingBooking(int $sessionId, int $customerId, GenericData $genericData): ?ClassSessionBooking
    {
        return ClassSessionBooking::where('account_id', $genericData->userData->account_id)
            ->where('class_schedule_session_id', $sessionId)
            ->where('customer_id', $customerId)
            ->first();
    }

    /**
     * Count all bookings for a session (excluding cancelled)
     * Used for capacity checking
     *
     * @param int $sessionId
     * @param GenericData $genericData
     * @return int
     */
    public function getBookingsCount(int $sessionId, GenericData $genericData): int
    {
        return ClassSessionBooking::where('account_id', $genericData->userData->account_id)
            ->where('class_schedule_session_id', $sessionId)
            ->where('status', '!=', ClassSessionBookingStatusConstant::STATUS_CANCELLED)
            ->count();
    }

    /**
     * Get booking sessions by date range for calendar view
     *
     * @param GenericData $genericData
     * @return Collection
     */
    public function getBookingSessionsByDateRange(GenericData $genericData): Collection
    {
        $query = ClassSessionBooking::where('account_id', $genericData->userData->account_id)
            ->with(['classScheduleSession.classSchedule.coach', 'customer'])
            ->whereHas('classScheduleSession', function ($q) use ($genericData) {
                $q->whereDate('start_time', '>=', $genericData->getData()->startDate);
            })
            ->whereHas('classScheduleSession', function ($q) use ($genericData) {
                $q->whereDate('start_time', '<=', $genericData->getData()->endDate);
            });

        return $query->get();
    }
}
