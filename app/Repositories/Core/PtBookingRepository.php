<?php

namespace App\Repositories\Core;

use App\Constant\ClassSessionBookingStatusConstant;
use App\Helpers\GenericData;
use App\Models\Account\ClassScheduleSession;
use App\Models\Core\PtBooking;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PtBookingRepository
{
     /**
     * Get PT bookings by date range for calendar view
     *
     * @param GenericData $genericData
     * @return Collection
     */
    public function getPtBookingsByDateRange(GenericData $genericData): Collection
    {
        $query = PtBooking::where('account_id', $genericData->userData->account_id)
            ->whereDate('booking_date', '>=', $genericData->getData()->startDate)
            ->whereDate('booking_date', '<=', $genericData->getData()->endDate);

        // Apply relations, filters, and sorts using GenericData methods
        $query = $genericData->applyRelations($query);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        return $query->get();
    }
    /**
     * Get PT bookings by coach ID and date range
     *
     * @param GenericData $genericData
     * @param int $coachId
     * @return Collection
     */
    public function getCoachPtBookings(GenericData $genericData, int $coachId): Collection
    {
        $query = PtBooking::where('account_id', $genericData->userData->account_id)
            ->where('coach_id', $coachId)
            ->whereDate('booking_date', '>=', $genericData->getData()->startDate)
            ->whereDate('booking_date', '<=', $genericData->getData()->endDate);

        // Apply relations, filters, and sorts using GenericData methods
        $query = $genericData->applyRelations($query);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        return $query->get();
    }

    /**
     * Find a PT booking by ID
     *
     * @param int $accountId
     * @param int $id
     * @return PtBooking
     */
    public function findPtBookingById(int $accountId, int $id): PtBooking
    {
        return PtBooking::where('account_id', $accountId)
            ->where('id', $id)
            ->firstOrFail();
    }

    /**
     * Create a new PT booking
     *
     * @param GenericData $genericData
     * @return PtBooking
     */
    public function createPtBooking(GenericData $genericData): PtBooking
    {
        // Ensure account_id is set in data
        $genericData->getData()->account_id = $genericData->userData->account_id;
        $genericData->getData()->created_by = $genericData->userData->id;
        $genericData->getData()->status = ClassSessionBookingStatusConstant::STATUS_BOOKED;
        $genericData->getData()->updated_by = $genericData->userData->id;
        $genericData->syncDataArray();
        return PtBooking::create($genericData->data)->fresh();
    }

    /**
     * Update a PT booking with class schedule id
     *
     * @param int $id
     * @param int $classScheduleId
     * @return PtBooking
     */
    public function updatePtBookingWithClassScheduleId(int $accountId, int $id, int $classScheduleId): PtBooking
    {
        $ptBooking = $this->findPtBookingById($accountId, $id);
        $ptBooking->class_schedule_id = $classScheduleId;
        $ptBooking->save();

        return $ptBooking->fresh();
    }

    /**
     * Update a PT booking
     *
     * @param int $id
     * @param GenericData $genericData
     * @return PtBooking
     */
    public function updatePtBooking(int $id, GenericData $genericData): PtBooking
    {
        $ptBooking = $this->findPtBookingById($genericData->userData->account_id, $id);
        $genericData->getData()->updated_by = $genericData->userData->id;
        $genericData->syncDataArray();
        $ptBooking->update($genericData->data);
        return $ptBooking->fresh();
    }

    /**
     * Mark a PT booking as cancelled
     *
     * @param int $id
     * @param GenericData $genericData
     * @return PtBooking
     */
    public function markAsCancelled(int $id, GenericData $genericData): PtBooking
    {
        $ptBooking = $this->findPtBookingById($genericData->userData->account_id, $id);
        $oldStatus = $ptBooking->status;
        $ptBooking->status = ClassSessionBookingStatusConstant::STATUS_CANCELLED;
        $ptBooking->updated_by = $genericData->userData->id;
        $ptBooking->save();
        return $ptBooking->fresh();
    }

    /**
     * Mark a PT booking as attended
     *
     * @param int $id
     * @param GenericData $genericData
     * @return PtBooking
     */
    public function markAsAttended(int $id, GenericData $genericData): PtBooking
    {
        $ptBooking = $this->findPtBookingById($genericData->userData->account_id, $id);
        $ptBooking->status = ClassSessionBookingStatusConstant::STATUS_ATTENDED;
        $ptBooking->updated_by = $genericData->userData->id;
        $ptBooking->save();
        return $ptBooking->fresh();
    }

    /**
     * Mark a PT booking as no show
     *
     * @param int $id
     * @param GenericData $genericData
     * @return PtBooking
     */
    public function markAsNoShow(int $id, GenericData $genericData): PtBooking
    {
        $ptBooking = $this->findPtBookingById($genericData->userData->account_id, $id);
        $ptBooking->status = ClassSessionBookingStatusConstant::STATUS_NO_SHOW;
        $ptBooking->updated_by = $genericData->userData->id;
        $ptBooking->save();
        return $ptBooking->fresh();
    }

    /**
     * Get PT bookings by class schedule session ID
     * Matches PT bookings to a session by class_schedule_id, date, time, and coach
     *
     * @param int $sessionId
     * @param int $accountId
     * @return Collection
     */
    public function getPtBookingsByClassScheduleSession(int $sessionId, int $accountId): Collection
    {
        $session = ClassScheduleSession::where('id', $sessionId)
            ->where('account_id', $accountId)
            ->with('classSchedule')
            ->firstOrFail();

        $sessionDate = Carbon::parse($session->start_time)->format('Y-m-d');

        return PtBooking::where('class_schedule_id', $session->classSchedule->id)
            ->where('booking_date', $sessionDate)
            ->where('coach_id', $session->classSchedule->coach_id)
            ->where('account_id', $accountId)
            ->with(['customer', 'coach', 'ptPackage'])
            ->get();
    }

    /**
     * Get upcoming PT bookings for a customer
     * Returns all future bookings that are not cancelled
     *
     * @param GenericData $genericData
     * @param int $customerId
     * @return Collection
     */
    public function getCustomerUpcomingPtBookings(GenericData $genericData, int $customerId): Collection
    {
        $today = Carbon::now()->toDateString();

        $query = PtBooking::where('account_id', $genericData->userData->account_id)
            ->where('customer_id', $customerId)
            ->whereDate('booking_date', '>=', $today)
            ->where('status', '!=', ClassSessionBookingStatusConstant::STATUS_CANCELLED);

        // Apply relations, filters, and sorts using GenericData methods
        $query = $genericData->applyRelations($query);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        return $query->get();
    }

    /**
     * Get paginated PT booking history for a customer
     * Returns past bookings or completed/cancelled bookings with pagination
     *
     * @param GenericData $genericData
     * @param int $customerId
     * @return LengthAwarePaginator
     */
    public function getCustomerPtBookingHistory(GenericData $genericData, int $customerId): LengthAwarePaginator
    {
        $today = Carbon::now()->toDateString();

        $query = PtBooking::where('account_id', $genericData->userData->account_id)
            ->where('customer_id', $customerId)
            ->where(function ($q) use ($today) {
                $q->whereDate('booking_date', '<', $today)
                  ->orWhereIn('status', [
                      ClassSessionBookingStatusConstant::STATUS_ATTENDED,
                      ClassSessionBookingStatusConstant::STATUS_NO_SHOW,
                      ClassSessionBookingStatusConstant::STATUS_CANCELLED
                  ]);
            });

        // Apply relations, filters, and sorts using GenericData methods
        $query = $genericData->applyRelations($query);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
    }
}
