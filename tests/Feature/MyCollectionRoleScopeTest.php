<?php

namespace Tests\Feature;

use App\Http\Controllers\Core\MyCollectionController;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;

/**
 * My Collection role-scope tests. Uses in-memory user objects and mocks (no DB) so they run without SQLite driver.
 * When account_id is missing or invalid, controller returns 400.
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
     * Build a user-like object. account_id optional: pass null to test "account required" path.
     *
     * @param int|null $accountId
     * @param string $role
     * @param int $id
     * @return object
     */
    protected function createUser(?int $accountId, string $role, int $id = 1): object
    {
        $u = (object) [
            'id' => $id,
            'role' => $role,
            'firstname' => 'Test',
            'lastname' => ucfirst($role),
        ];
        if ($accountId !== null) {
            $u->account_id = $accountId;
        }
        return $u;
    }

    protected function requestWithUser(?object $user): Request
    {
        $request = Request::create('/dashboard/my-collection', 'GET');
        if ($user !== null) {
            $request->attributes->set('user', $user);
        }
        return $request;
    }

    public function test_admin_gets_200_and_data(): void
    {
        $mockData = [
            'trainerStats' => [],
            'weeklyEarnings' => [],
            'earningsBreakdown' => [],
            'monthlyProgress' => [],
            'recentSessions' => [],
        ];
        $this->mock(\App\Services\Core\MyCollectionService::class, function ($mock) use ($mockData) {
            $mock->shouldReceive('getStats')->once()->with(1, null)->andReturn($mockData);
        });

        $admin = $this->createUser(1, 'admin');
        $request = $this->requestWithUser($admin);
        $controller = app(MyCollectionController::class);

        $response = $controller->getStats($request);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $response->getData(true);
        $this->assertTrue($json['success'] ?? false);
        $data = $json['data'] ?? [];
        $this->assertArrayHasKey('trainerStats', $data);
        $this->assertArrayHasKey('weeklyEarnings', $data);
        $this->assertArrayHasKey('earningsBreakdown', $data);
        $this->assertArrayHasKey('monthlyProgress', $data);
        $this->assertArrayHasKey('recentSessions', $data);
    }

    public function test_coach_gets_200_and_data(): void
    {
        $this->mock(\App\Services\Core\MyCollectionService::class, function ($mock) {
            $mock->shouldReceive('getStats')->once()->with(1, 99)->andReturn(['trainerStats' => [], 'weeklyEarnings' => [], 'earningsBreakdown' => [], 'monthlyProgress' => [], 'recentSessions' => []]);
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

    public function test_staff_gets_403(): void
    {
        $staff = $this->createUser(1, 'staff');
        $request = $this->requestWithUser($staff);
        $controller = app(MyCollectionController::class);

        $response = $controller->getStats($request);

        $this->assertEquals(403, $response->getStatusCode());
        $json = $response->getData(true);
        $this->assertFalse($json['success'] ?? true);
    }

    public function test_unauthorized_no_user_gets_401(): void
    {
        $request = $this->requestWithUser(null);
        $controller = app(MyCollectionController::class);

        $response = $controller->getStats($request);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_account_required_when_missing_returns_400(): void
    {
        $adminNoAccount = $this->createUser(null, 'admin');
        $request = $this->requestWithUser($adminNoAccount);
        $controller = app(MyCollectionController::class);

        $response = $controller->getStats($request);

        $this->assertEquals(400, $response->getStatusCode());
        $json = $response->getData(true);
        $this->assertFalse($json['success'] ?? true);
        $this->assertStringContainsString('Account', $json['message'] ?? '');
    }

    public function test_account_boundary_uses_user_account_id(): void
    {
        $this->mock(\App\Services\Core\MyCollectionService::class, function ($mock) {
            $mock->shouldReceive('getStats')->once()->with(2, null)->andReturn(['trainerStats' => [], 'recentSessions' => []]);
        });

        $userAccount2 = $this->createUser(2, 'admin', 2);
        $request = $this->requestWithUser($userAccount2);
        $controller = app(MyCollectionController::class);

        $response = $controller->getStats($request);

        $this->assertEquals(200, $response->getStatusCode());
        $json = $response->getData(true);
        $this->assertTrue($json['success'] ?? false);
        $data = $json['data'] ?? [];
        $this->assertArrayHasKey('trainerStats', $data);
        $this->assertArrayHasKey('recentSessions', $data);
    }
}
