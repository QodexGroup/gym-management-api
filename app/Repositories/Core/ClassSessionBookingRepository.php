<?php

namespace App\Repositories\Core;

use App\Constant\ClassSessionBookingStatusConstant;
use App\Helpers\GenericData;
use App\Models\Core\ClassSessionBooking;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

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
     * Update a booking (customer, session, notes)
     *
     * @param int $id
     * @param GenericData $genericData
     * @return ClassSessionBooking|null
     */
    public function updateBooking(int $id, GenericData $genericData): ?ClassSessionBooking
    {
        $booking = ClassSessionBooking::where('id', $id)
            ->where('account_id', $genericData->userData->account_id)
            ->firstOrFail();

        // Merge updated_by with the data array
        $updateData = array_merge($genericData->data, [
            'updated_by' => $genericData->userData->id,
            'class_schedule_session_id' => $genericData->getData()->sessionId,
        ]);

        $booking->update($updateData);
        return $booking->fresh();
    }

    /**
     * Get a booking by ID
     *
     * @param int $id
     * @param GenericData $genericData
     * @return ClassSessionBooking|null
     */
    public function findBookingById(int $id, GenericData $genericData): ?ClassSessionBooking
    {
        return ClassSessionBooking::where('id', $id)
            ->where('account_id', $genericData->userData->account_id)
            ->with(['customer', 'classScheduleSession.classSchedule'])
            ->first();
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

    /**
     * Count attended bookings for a coach in date range (session start_time in range).
     *
     * @param int $accountId
     * @param int $coachId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return int
     */
    public function countAttendedByCoachAndDateRange(int $accountId, int $coachId, string $dateFrom, string $dateTo): int
    {
        return ClassSessionBooking::where('account_id', $accountId)
            ->where('status', ClassSessionBookingStatusConstant::STATUS_ATTENDED)
            ->whereHas('classScheduleSession.classSchedule', function ($q) use ($coachId) {
                $q->where('coach_id', $coachId);
            })
            ->whereHas('classScheduleSession', function ($q) use ($dateFrom, $dateTo) {
                $q->whereDate('start_time', '>=', $dateFrom)
                  ->whereDate('start_time', '<=', $dateTo);
            })
            ->count();
    }

    /**
     * Recent attended sessions for a coach (for My Collection).
     *
     * @param int $accountId
     * @param int $coachId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentAttendedByCoach(int $accountId, int $coachId, int $limit = 10): Collection
    {
        return ClassSessionBooking::where('account_id', $accountId)
            ->where('status', ClassSessionBookingStatusConstant::STATUS_ATTENDED)
            ->whereHas('classScheduleSession.classSchedule', function ($q) use ($coachId) {
                $q->where('coach_id', $coachId);
            })
            ->with(['customer', 'classScheduleSession.classSchedule'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }
}
