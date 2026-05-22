<?php

namespace App\Services\Account;

use App\Constant\ClassTypeScheduleConstant;
use App\Constant\UserStatusConstant;
use App\Models\Account\ClassSchedule;
use App\Models\Account\ClassScheduleSession;

final class GroupClassSessionAuthorizationService
{
    /** @var list<string> */
    private static array $forbiddenMessages = [
        'You must assign yourself as the coach when creating a group class schedule.',
        'You cannot assign a different coach for this group class schedule.',
        'You can only manage your own group classes.',
        'You are not authorized to manage group class schedules.',
    ];

    public static function isForbiddenAuthorizationMessage(?string $message): bool
    {
        return $message !== null && in_array($message, self::$forbiddenMessages, true);
    }

    /**
     * On group class schedule create: coaches may only specify themselves as coachId.
     *
     * @param object{id?: int|string|null, role?: string|null} $user
     *
     * @throws \Exception
     */
    public function assertCoachSelfAssignCoachIdOnCreate(object $user, int $coachIdFromRequest): void
    {
        if (($user->role ?? null) !== UserStatusConstant::COACH) {
            return;
        }
        if ((int) ($user->id ?? 0) !== $coachIdFromRequest) {
            throw new \Exception(self::$forbiddenMessages[0]);
        }
    }

    /**
     * Group class schedule updates: coaches may not change scheduled coach via payload.
     *
     * @param object{id?: int|string|null, role?: string|null} $user
     *
     * @throws \Exception
     */
    public function assertCoachGroupScheduleUpdateCoachIdAllowed(object $user, ?int $coachIdFromPayload): void
    {
        if (($user->role ?? null) !== UserStatusConstant::COACH) {
            return;
        }
        if ($coachIdFromPayload === null) {
            return;
        }
        if ((int) ($coachIdFromPayload) !== (int) ($user->id ?? 0)) {
            throw new \Exception(self::$forbiddenMessages[1]);
        }
    }

    /**
     * @param object{id?: int|string|null, role?: string|null} $user
     *
     * @throws \Exception
     */
    public function assertMayManageExistingGroupSchedule(object $user, ClassSchedule $schedule): void
    {
        if ($schedule->class_type !== ClassTypeScheduleConstant::GROUP_CLASS) {
            return;
        }

        $role = (string) ($user->role ?? '');

        if (in_array($role, [UserStatusConstant::ADMIN, UserStatusConstant::STAFF], true)) {
            return;
        }

        if ($role === UserStatusConstant::COACH) {
            if ((int) $schedule->coach_id !== (int) ($user->id ?? 0)) {
                throw new \Exception(self::$forbiddenMessages[2]);
            }

            return;
        }

        throw new \Exception(self::$forbiddenMessages[3]);
    }

    /**
     * @param object{id?: int|string|null, role?: string|null} $user
     *
     * @throws \Exception
     */
    public function assertMayManageGroupClassSession(object $user, ClassScheduleSession $session): void
    {
        $session->loadMissing('classSchedule');
        $schedule = $session->classSchedule;
        if ($schedule === null) {
            throw new \Exception(self::$forbiddenMessages[3]);
        }

        $this->assertMayManageExistingGroupSchedule($user, $schedule);
    }
}
