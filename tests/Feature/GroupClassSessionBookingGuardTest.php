<?php

namespace Tests\Feature;

use App\Constant\CustomerMembershipConstant;
use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use App\Repositories\Account\ClassScheduleSessionRepository;
use App\Repositories\Core\ClassSessionBookingRepository;
use App\Repositories\Core\CustomerRepository;
use App\Services\Core\ClassSessionBookingService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;

/** Deactivated membership guard for group class booking (mock data, no DB). */
class GroupClassSessionBookingGuardTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function createApplication(): Application
    {
        $app = require __DIR__ . '/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    private function bookingService(CustomerRepository $customerRepository): ClassSessionBookingService
    {
        return new ClassSessionBookingService(
            Mockery::mock(ClassSessionBookingRepository::class),
            Mockery::mock(ClassScheduleSessionRepository::class),
            $customerRepository
        );
    }

    private function customerWithMembershipStatus(string $status): Customer
    {
        $membership = new CustomerMembership(['status' => $status]);
        $customer = new Customer(['id' => 10, 'account_id' => 1]);
        $customer->setRelation('currentMembership', $membership);

        return $customer;
    }

    public function test_book_session_blocked_for_deactivated_membership(): void
    {
        $customerRepository = Mockery::mock(CustomerRepository::class);
        $customerRepository
            ->shouldReceive('findCustomerByIdWithCurrentMembership')
            ->once()
            ->with(10, 1)
            ->andReturn($this->customerWithMembershipStatus(CustomerMembershipConstant::STATUS_DEACTIVATED));

        $service = $this->bookingService($customerRepository);

        try {
            $service->ensureCustomerMayJoinGroupClassSession(10, 1);
            $this->fail('Expected deactivated membership exception.');
        } catch (\Exception $e) {
            $this->assertSame(ClassSessionBookingService::CLIENT_DEACTIVATED_CANNOT_JOIN_MESSAGE, $e->getMessage());
        }
    }

    public function test_active_membership_may_join_group_class(): void
    {
        $customerRepository = Mockery::mock(CustomerRepository::class);
        $customerRepository
            ->shouldReceive('findCustomerByIdWithCurrentMembership')
            ->once()
            ->with(10, 1)
            ->andReturn($this->customerWithMembershipStatus(CustomerMembershipConstant::STATUS_ACTIVE));

        $service = $this->bookingService($customerRepository);

        $this->expectNotToPerformAssertions();
        $service->ensureCustomerMayJoinGroupClassSession(10, 1);
    }

    public function test_customer_not_found_cannot_join_group_class(): void
    {
        $customerRepository = Mockery::mock(CustomerRepository::class);
        $customerRepository
            ->shouldReceive('findCustomerByIdWithCurrentMembership')
            ->once()
            ->with(99, 1)
            ->andReturn(null);

        $service = $this->bookingService($customerRepository);

        try {
            $service->ensureCustomerMayJoinGroupClassSession(99, 1);
            $this->fail('Expected customer not found exception.');
        } catch (\Exception $e) {
            $this->assertSame('Customer not found', $e->getMessage());
        }
    }
}
