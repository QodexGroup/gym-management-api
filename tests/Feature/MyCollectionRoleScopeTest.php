<?php

namespace Tests\Feature;

use App\Http\Controllers\Core\MyCollectionController;
use App\Http\Requests\GenericRequest;
use App\Models\User;
use App\Services\Core\MyCollectionService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Application;

/**
 * My Collection controller tests (no DB).
 * Uses in-memory user objects and mocks (no DB) so they run without SQLite driver.
 */
class MyCollectionRoleScopeTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('TEST_ACCOUNT_ID')) {
            define('TEST_ACCOUNT_ID', 1);
        }
    }

    public function createApplication(): Application
    {
        $app = require __DIR__ . '/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }
    /**
     * Build a User model instance (no DB).
     *
     * @param int $accountId
     * @param string $role
     * @param int $id
     * @return User
     */
    protected function createUser(int $accountId, string $role, int $id = 1): User
    {
        $user = new User();
        $user->id = $id;
        $user->role = $role;
        $user->firstname = 'Test';
        $user->lastname = ucfirst($role);
        $user->account_id = $accountId;

        return $user;
    }

    protected function requestWithUser(?User $user): GenericRequest
    {
        $request = GenericRequest::create('/dashboard/my-collection', 'GET');
        if ($user !== null) {
            $request->attributes->set('user', $user);
        }
        return $request;
    }

    public function test_admin_gets_200_and_data(): void
    {
        $this->mock(MyCollectionService::class, function ($mock) {
            $mock->shouldReceive('getStats')->once()->with(1, 1)->andReturn([
                'trainerStats' => [],
                'weeklyEarnings' => [],
                'earningsBreakdown' => [],
                'monthlyProgress' => [],
                'recentPayments' => [],
            ]);
        });

        $admin = $this->createUser(1, 'admin', 1);
        $request = $this->requestWithUser($admin);
        $controller = app(MyCollectionController::class);

        $response = $controller->getStats($request);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $response->getData(true);
        $this->assertTrue($json['success'] ?? false);
        $this->assertArrayHasKey('data', $json);
    }

    public function test_coach_gets_200_and_data(): void
    {
        $this->mock(MyCollectionService::class, function ($mock) {
            $mock->shouldReceive('getStats')->once()->with(1, 99)->andReturn(['trainerStats' => [], 'weeklyEarnings' => [], 'earningsBreakdown' => [], 'monthlyProgress' => [], 'recentPayments' => []]);
        });

        $coach = $this->createUser(1, 'coach', 99);
        $request = $this->requestWithUser($coach);
        $controller = app(MyCollectionController::class);

        $response = $controller->getStats($request);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $response->getData(true);
        $this->assertTrue($json['success'] ?? false);
        $this->assertArrayHasKey('data', $json);
    }

    public function test_staff_gets_200_and_data(): void
    {
        $this->mock(MyCollectionService::class, function ($mock) {
            $mock->shouldReceive('getStats')->once()->with(1, 1)->andReturn([
                'trainerStats' => [],
                'weeklyEarnings' => [],
                'earningsBreakdown' => [],
                'monthlyProgress' => [],
                'recentPayments' => [],
            ]);
        });

        $staff = $this->createUser(1, 'staff', 1);
        $request = $this->requestWithUser($staff);
        $controller = app(MyCollectionController::class);

        $response = $controller->getStats($request);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $response->getData(true);
        $this->assertTrue($json['success'] ?? false);
        $this->assertArrayHasKey('data', $json);
    }

    public function test_account_boundary_uses_user_account_id(): void
    {
        $this->mock(MyCollectionService::class, function ($mock) {
            $mock->shouldReceive('getStats')->once()->with(2, 2)->andReturn(['trainerStats' => [], 'recentPayments' => []]);
        });

        $userAccount2 = $this->createUser(2, 'coach', 2);
        $request = $this->requestWithUser($userAccount2);
        $controller = app(MyCollectionController::class);

        $response = $controller->getStats($request);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $response->getData(true);
        $this->assertTrue($json['success'] ?? false);
        $data = $json['data'] ?? [];
        $this->assertArrayHasKey('trainerStats', $data);
        $this->assertArrayHasKey('recentPayments', $data);
    }
}
