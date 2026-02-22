<?php

namespace App\Services\Account;

use App\Constant\RecurringIntervalConstant;
use App\Constant\ScheduleTypeConstant;
use App\Helpers\GenericData;
use App\Models\Account\ClassSchedule;
use App\Services\Account\AccountLimitService;
use App\Models\Account\ClassScheduleSession;
use App\Repositories\Account\ClassScheduleRepository;
use App\Repositories\Account\ClassScheduleSessionRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClassScheduleService
{
    public function __construct(
        private ClassScheduleRepository $classScheduleRepository,
        private ClassScheduleSessionRepository $sessionRepository,
        private AccountLimitService $accountLimitService
    ) {
    }

    /**
     * Create a new class schedule and generate sessions
     *
     * @param GenericData $genericData
     * @return ClassSchedule
     */
    public function createClassSchedule(GenericData $genericData): ClassSchedule
    {
        $check = $this->accountLimitService->canCreate($genericData->userData->account_id, AccountLimitService::RESOURCE_CLASS_SCHEDULES);
        if (!$check['allowed']) {
            throw new \Exception($check['message'] ?? 'Limit reached');
        }

        try {
            return DB::transaction(function () use ($genericData) {
                // Create the class schedule
                $schedule = $this->classScheduleRepository->createClassSchedule($genericData);

                // Generate sessions based on schedule type
                $this->generateSessions($genericData, $schedule->id);

                return $schedule->fresh(['sessions', 'coach']);
            });
        } catch (\Throwable $th) {
            Log::error('Error creating class schedule', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }

    /**
     * Update a class schedule and regenerate sessions if needed
     *
     * @param int $id
     * @param GenericData $genericData
     * @return ClassSchedule
     */
    public function updateClassSchedule(int $id, GenericData $genericData): ClassSchedule
    {
        try {
            return DB::transaction(function () use ($id, $genericData) {
                // Update the schedule
                $schedule = $this->classScheduleRepository->updateClassSchedule($id, $genericData);
                $schedule->refresh();

                // Delete existing sessions
                $this->sessionRepository->deleteSessionsByScheduleId(
                    $schedule->id,
                    $genericData->userData->account_id
                );

                // Regenerate sessions with updated schedule data
                $this->generateSessions($genericData, $schedule->id);

                return $schedule->fresh(['sessions', 'coach']);
            });
        } catch (\Throwable $th) {
            Log::error('Error updating class schedule', [
                'error' => $th->getMessage(),
                'schedule_id' => $id,
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }

    /**
     * Generate sessions based on schedule type
     *
     * @param GenericData $genericData
     * @param int $scheduleId
     * @return void
     */
    private function generateSessions(GenericData $genericData, int $scheduleId): void
    {
        $data = $genericData->getData();

        $startDate = Carbon::parse($data->startDate);
        $duration = $data->duration;
        $scheduleType = $data->scheduleType;
        $recurringInterval = $data->recurringInterval;
        $numberOfSessions = $data->numberOfSessions;

        if ($scheduleType == ScheduleTypeConstant::ONE_TIME) {
            // One-time schedule: create single session
            $endDate = $startDate->copy()->addMinutes($duration);
            $this->createSession($genericData, $scheduleId, $startDate, $endDate);
        } else {
            // Recurring schedule: create multiple sessions
            for ($i = 0; $i < $numberOfSessions; $i++) {
                $sessionStart = $this->calculateNextSessionDate($startDate, $recurringInterval, $i);
                $sessionEnd = $sessionStart->copy()->addMinutes($duration);
                $this->createSession($genericData, $scheduleId, $sessionStart, $sessionEnd);
            }
        }
    }

    /**
     * Create a session
     *
     * @param GenericData $genericData
     * @param int $scheduleId
     * @param Carbon $startTime
     * @param Carbon $endTime
     * @return void
     */
    private function createSession(GenericData $genericData, int $scheduleId, Carbon $startTime, Carbon $endTime): void
    {
        $this->sessionRepository->createSession([
            'account_id' => $genericData->userData->account_id,
            'class_schedule_id' => $scheduleId,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'attendance_count' => 0,
            'created_by' => $genericData->userData->id,
            'updated_by' => $genericData->userData->id,
        ]);
    }

    /**
     * Update a class schedule session (individual session update)
     *
     * @param int $sessionId
     * @param GenericData $genericData
     * @return ClassScheduleSession
     */
    public function updateClassScheduleSession(int $sessionId, GenericData $genericData): ClassScheduleSession
    {
        try {
            return DB::transaction(function () use ($sessionId, $genericData) {
                $accountId = $genericData->userData->account_id;
                $data = $genericData->getData();

                $updateData = [
                    'updated_by' => $genericData->userData->id,
                ];
                // Update start_time if provided
                if (isset($data->startTime)) {
                    $updateData['start_time'] = Carbon::parse($data->startTime);

                }
                if (isset($data->startTime) && isset($data->duration)) {
                    // Calculate end_time from start_time and duration
                    $startTime = Carbon::parse($data->startTime);
                    $updateData['end_time'] = $startTime->copy()->addMinutes($data->duration);
                }
                 $session = $this->sessionRepository->updateSession($sessionId, $updateData, $accountId);

                return $session;
            });
        } catch (\Throwable $th) {
            Log::error('Error updating class schedule session', [
                'error' => $th->getMessage(),
                'session_id' => $sessionId,
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }

    /**
     * Delete a class schedule and its associated sessions
     *
     * @param int $id
     * @param GenericData $genericData
     * @return bool
     */
    public function deleteClassSchedule(int $id, GenericData $genericData): bool
    {
        try {
            return DB::transaction(function () use ($id, $genericData) {
                $accountId = $genericData->userData->account_id;
                // Delete all associated sessions first
                $this->sessionRepository->deleteSessionsByScheduleId($id, $accountId);

                // Delete the class schedule
                return $this->classScheduleRepository->deleteClassSchedule($id, $accountId);
            });
        } catch (\Throwable $th) {
            Log::error('Error deleting class schedule', [
                'error' => $th->getMessage(),
                'schedule_id' => $id,
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }

    /**
     * Calculate the next session date based on recurring interval
     *
     * @param Carbon $startDate
     * @param string $interval
     * @param int $sessionNumber
     * @return Carbon
     */
    private function calculateNextSessionDate(Carbon $startDate, string $interval, int $sessionNumber): Carbon
    {
        $date = $startDate->copy();

        switch (strtolower($interval)) {
            case RecurringIntervalConstant::WEEKLY:
                return $date->addWeeks($sessionNumber);
            case RecurringIntervalConstant::BI_WEEKLY:
                return $date->addWeeks($sessionNumber * 2);
            case RecurringIntervalConstant::MONTHLY:
                return $date->addMonths($sessionNumber);
            default:
                // Default to weekly if unknown interval
                return $date->addWeeks($sessionNumber);
        }
    }
}
