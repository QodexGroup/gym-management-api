<?php

namespace Tests\Feature;

use App\Constant\ClassTypeScheduleConstant;
use App\Constant\UserStatusConstant;
use App\Models\Account\ClassSchedule;
use App\Services\Account\GroupClassSessionAuthorizationService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/** Authorization guards for group class schedules/sessions (no DB). */
class GroupClassSessionAuthorizationTest extends BaseTestCase
{
    protected function authorizationService(): GroupClassSessionAuthorizationService
    {
        return app(GroupClassSessionAuthorizationService::class);
    }

    public function createApplication(): Application
    {
        $app = require __DIR__ . '/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    private function userLike(int $id, string $role): object
    {
        return (object) ['id' => $id, 'role' => $role];
    }

    public function test_personal_training_schedule_skips_group_class_coach_restriction(): void
    {
        $schedule = new ClassSchedule([
            'coach_id' => 999,
            'class_type' => ClassTypeScheduleConstant::PERSONAL_TRAINING,
        ]);

        $this->expectNotToPerformAssertions();
        $this->authorizationService()->assertMayManageExistingGroupSchedule(
            $this->userLike(1, UserStatusConstant::COACH),
            $schedule
        );
    }

    public function test_admin_may_manage_group_schedule_for_any_coach(): void
    {
        $schedule = new ClassSchedule([
            'coach_id' => 50,
            'class_type' => ClassTypeScheduleConstant::GROUP_CLASS,
        ]);

        $this->expectNotToPerformAssertions();
        $this->authorizationService()->assertMayManageExistingGroupSchedule(
            $this->userLike(1, UserStatusConstant::ADMIN),
            $schedule
        );
    }

    public function test_coach_cannot_manage_other_coach_group_schedule(): void
    {
        $schedule = new ClassSchedule([
            'coach_id' => 500,
            'class_type' => ClassTypeScheduleConstant::GROUP_CLASS,
        ]);

        try {
            $this->authorizationService()->assertMayManageExistingGroupSchedule(
                $this->userLike(404, UserStatusConstant::COACH),
                $schedule
            );
            $this->fail('Expected authorization exception.');
        } catch (\Exception $e) {
            $this->assertTrue(GroupClassSessionAuthorizationService::isForbiddenAuthorizationMessage($e->getMessage()));
        }
    }

    public function test_coach_create_must_assign_self_as_coach(): void
    {
        try {
            $this->authorizationService()->assertCoachSelfAssignCoachIdOnCreate(
                $this->userLike(7, UserStatusConstant::COACH),
                99
            );
            $this->fail('Expected authorization exception.');
        } catch (\Exception $e) {
            $this->assertTrue(GroupClassSessionAuthorizationService::isForbiddenAuthorizationMessage($e->getMessage()));
        }
    }

    public function test_coach_update_cannot_assign_different_coach(): void
    {
        try {
            $this->authorizationService()->assertCoachGroupScheduleUpdateCoachIdAllowed(
                $this->userLike(7, UserStatusConstant::COACH),
                99
            );
            $this->fail('Expected authorization exception.');
        } catch (\Exception $e) {
            $this->assertTrue(GroupClassSessionAuthorizationService::isForbiddenAuthorizationMessage($e->getMessage()));
        }
    }
}
