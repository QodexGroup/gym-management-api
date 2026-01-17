<?php

namespace App\Repositories\Account;

use App\Constant\ClassTypeScheduleConstant;
use App\Helpers\GenericData;
use App\Models\Account\ClassSchedule;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClassScheduleRepository
{
    /**
     * Get all class schedules
     *
     * @param GenericData $genericData
     * @return LengthAwarePaginator|Collection
     */
    public function getAllClassSchedules(GenericData $genericData): LengthAwarePaginator|Collection
    {
        $query = ClassSchedule::where('account_id', $genericData->userData->account_id)
            ->where('start_date', '>=', Carbon::now()->startOfDay());

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
     * Get class schedules by coach ID
     *
     * @param GenericData $genericData
     * @param int $coachId
     * @return LengthAwarePaginator|Collection
     */
    public function getClassSchedulesByCoachId(GenericData $genericData, int $coachId): LengthAwarePaginator|Collection
    {
        $query = ClassSchedule::where('account_id', $genericData->userData->account_id)
            ->where('coach_id', $coachId)
            ->where('start_date', '>=', Carbon::now()->startOfDay());

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
     * Get a class schedule by ID
     *
     * @param int $id
     * @param int $accountId
     * @return ClassSchedule
     */
    public function findClassScheduleById(int $id, int $accountId): ClassSchedule
    {
        return ClassSchedule::where('id', $id)
            ->where('account_id', $accountId)
            ->firstOrFail();
    }

    /**
     * Create a new class schedule
     *
     * @param GenericData $genericData
     * @return ClassSchedule
     */
    public function createClassSchedule(GenericData $genericData): ClassSchedule
    {
        // Ensure account_id is set in data
        $genericData->getData()->account_id = $genericData->userData->account_id;
        $genericData->getData()->class_type = ClassTypeScheduleConstant::GROUP_CLASS;
        $genericData->getData()->created_by = $genericData->userData->id;
        $genericData->getData()->updated_by = $genericData->userData->id;
        $genericData->syncDataArray();

        return ClassSchedule::create($genericData->data)->fresh();
    }

    /**
     * Update a class schedule
     *
     * @param int $id
     * @param GenericData $genericData
     * @return ClassSchedule
     */
    public function updateClassSchedule(int $id, GenericData $genericData): ClassSchedule
    {
        $schedule = $this->findClassScheduleById($id, $genericData->userData->account_id);
        $genericData->getData()->updated_by =  $genericData->userData->id;
        $genericData->syncDataArray();
        $schedule->update($genericData->data);
        return $schedule->fresh();
    }

    /**
     * Delete a class schedule (soft delete)
     *
     * @param int $id
     * @param int $accountId
     * @return bool
     */
    public function deleteClassSchedule(int $id, int $accountId): bool
    {
        $schedule = $this->findClassScheduleById($id, $accountId);
        return $schedule->delete();
    }
}
