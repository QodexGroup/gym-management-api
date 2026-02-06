<?php

namespace App\Repositories\Account;

use App\Constant\ClassSessionBookingStatusConstant;
use App\Helpers\GenericData;
use App\Models\Account\ClassScheduleSession;
use App\Models\Core\PtBooking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClassScheduleSessionRepository
{
    /**
     * Get all class schedule sessions
     *
     * @param GenericData $genericData
     * @return LengthAwarePaginator|Collection
     */
    public function getAllSessions(GenericData $genericData): LengthAwarePaginator|Collection
    {
        $accountId = $genericData->userData->account_id;
        $startDate = $genericData->filters['startDate'] ?? null;
        $endDate = $genericData->filters['endDate'] ?? null;
        $ptBookingTable = (new PtBooking())->getTable();

        // Remove date filters to avoid conflicts with applyFilters
        $filters = $genericData->filters;
        unset($filters['startDate'], $filters['endDate']);
        $genericData->filters = $filters;

        $query = ClassScheduleSession::query()
            ->where('account_id', $accountId)
            ->with('classSchedule')
            ->withCount([
                'bookings as attendance_count' => function ($q) {
                    $q->where('status', '!=', ClassSessionBookingStatusConstant::STATUS_CANCELLED);
                },
                'ptBookings as pt_attendance_count' => function ($q) use ($accountId, $ptBookingTable) {
                    $q->where("{$ptBookingTable}.account_id", $accountId)
                      ->where("{$ptBookingTable}.status", '!=', ClassSessionBookingStatusConstant::STATUS_CANCELLED);
                }
            ]);

        if ($startDate) {
            $query->whereDate('start_time', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('start_time', '<=', $endDate);
        }

        $query = $genericData->applyRelations($query);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        return $genericData->pageSize > 0
            ? $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page)
            : $query->get();
    }


    /**
     * Get sessions by class schedule ID
     *
     * @param int $classScheduleId
     * @param int $accountId
     * @return Collection
     */
    public function getSessionsByScheduleId(int $classScheduleId, int $accountId): Collection
    {
        return ClassScheduleSession::where('class_schedule_id', $classScheduleId)
            ->where('account_id', $accountId)
            ->get();
    }

    /**
     * Create a new session
     *
     * @param array $data
     * @return ClassScheduleSession
     */
    public function createSession(array $data): ClassScheduleSession
    {
        return ClassScheduleSession::create($data);
    }


    /**
     * Delete sessions by class schedule ID
     *
     * @param int $classScheduleId
     * @param int $accountId
     * @return bool
     */
    public function deleteSessionsByScheduleId(int $classScheduleId, int $accountId): bool
    {
        return ClassScheduleSession::where('class_schedule_id', $classScheduleId)
            ->where('account_id', $accountId)
            ->delete();
    }

    /**
     * Update a session
     *
     * @param int $id
     * @param array $data
     * @param int $accountId
     * @return ClassScheduleSession
     */
    public function updateSession(int $id, array $data, int $accountId): ClassScheduleSession
    {
        $session = ClassScheduleSession::where('id', $id)
            ->where('account_id', $accountId)
            ->firstOrFail();

        $session->update($data);
        return $session->fresh();
    }

    /**
     * Update attendance count on a session
     *
     * @param int $id
     * @param int $accountId
     * @return bool
     */
    public function updateAttendanceByClassScheduleId(int $classScheduleId, int $accountId): bool
    {
        return ClassScheduleSession::where('class_schedule_id', $classScheduleId)
            ->where('account_id', $accountId)
            ->increment('attendance_count');
    }

    /**
     * Update attendance count on a session
     *
     * @param int $classScheduleId
     * @param int $accountId
     * @return bool
     */
    public function updateAttendanceCountIncrementById(int $id, int $accountId): bool
    {
        return ClassScheduleSession::where('id', $id)
            ->where('account_id', $accountId)
            ->increment('attendance_count');
    }

        /**
     * Update attendance count on a session
     *
     * @param int $sessionId
     * @param int $accountId
     * @return bool
     */
    public function updateAttendanceCountSession(int $sessionId, int $accountId, $totalAttendanceCount): bool
    {
        $classScheduleSession = ClassScheduleSession::where('id', $sessionId)
            ->where('account_id', $accountId)
            ->firstOrFail();
        $classScheduleSession->attendance_count += $totalAttendanceCount;
        return $classScheduleSession->save();
    }

    /**
     * Get a session by ID
     *
     * @param int $id
     * @param int $accountId
     * @return ClassScheduleSession
     */
    public function getSessionById(int $id, int $accountId): ClassScheduleSession
    {
        return ClassScheduleSession::where('id', $id)
            ->where('account_id', $accountId)
            ->with('classSchedule')
            ->firstOrFail();
    }

    /**
     * Delete a session
     *
     * @param int $id
     * @param int $accountId
     * @return bool
     */
    public function deleteSession(int $id, int $accountId): bool
    {
        $session = ClassScheduleSession::where('id', $id)
            ->where('account_id', $accountId)
            ->firstOrFail();
        return $session->delete();
    }
}
