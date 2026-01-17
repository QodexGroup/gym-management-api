<?php

namespace App\Repositories\Account;

use App\Helpers\GenericData;
use App\Models\Account\ClassScheduleSession;
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
        $query = ClassScheduleSession::where('account_id', $genericData->userData->account_id);

        // Apply relations, filters, and sorts using GenericData methods
        $query = $genericData->applyRelations($query);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        // Check if pagination is requested
        if ($genericData->pageSize > 0) {
            return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
        }

        return $query->get();
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
