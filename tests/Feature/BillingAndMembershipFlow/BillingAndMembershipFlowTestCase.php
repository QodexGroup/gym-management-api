<?php

namespace Tests\Feature\BillingAndMembershipFlow;

use App\Models\Account\MembershipPlan;
use App\Models\Core\Customer;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use App\Models\User;
use App\Repositories\Account\MembershipPlanRepository;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerRepository;
use App\Services\Core\CustomerBillService;
use App\Services\Core\CustomerPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class BillingAndMembershipFlowTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected MembershipPlan $monthlyPlan;
    protected MembershipPlan $quarterlyPlan;
    protected CustomerBillService $billService;
    protected CustomerPaymentService $paymentService;
    protected CustomerBillRepository $billRepository;
    protected CustomerRepository $customerRepository;
    protected MembershipPlanRepository $membershipPlanRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user/account
        $this->user = User::create([
            'account_id' => 1,
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Create test customer
        $this->customer = Customer::create([
            'account_id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone_number' => '1234567890',
            'balance' => 0,
        ]);

        // Create test membership plans
        $this->monthlyPlan = MembershipPlan::create([
            'account_id' => 1,
            'plan_name' => 'Monthly Plan',
            'price' => 1000.00,
            'plan_period' => 1,
            'plan_interval' => 'months',
            'features' => [],
        ]);

        $this->quarterlyPlan = MembershipPlan::create([
            'account_id' => 1,
            'plan_name' => 'Quarterly Plan',
            'price' => 2500.00,
            'plan_period' => 3,
            'plan_interval' => 'months',
            'features' => [],
        ]);

        // Initialize services and repositories
        $this->billRepository = app(CustomerBillRepository::class);
        $this->customerRepository = app(CustomerRepository::class);
        $this->membershipPlanRepository = app(MembershipPlanRepository::class);
        $this->billService = app(CustomerBillService::class);
        $this->paymentService = app(CustomerPaymentService::class);
    }

    /**
     * Create GenericData for bill creation
     */
    protected function createBillGenericData(array $data): \App\Helpers\GenericData
    {
        $genericData = new \App\Helpers\GenericData();
        $genericData->userData = $this->user;
        $genericData->data = array_merge([
            'customerId' => $this->customer->id,
            'accountId' => 1,
            'billDate' => $data['billDate'] ?? \Carbon\Carbon::now()->toDateString(),
            'discountPercentage' => $data['discountPercentage'] ?? 0,
        ], $data);
        $genericData->syncDataArray();
        return $genericData;
    }

    /**
     * Create GenericData for payment
     */
    protected function createPaymentGenericData(int $billId, float $amount, ?string $paymentDate = null): \App\Helpers\GenericData
    {
        $genericData = new \App\Helpers\GenericData();
        $genericData->userData = $this->user;
        $genericData->data = [
            'customerId' => $this->customer->id,
            'customerBillId' => $billId,
            'amount' => $amount,
            'paymentMethod' => 'cash',
            'paymentDate' => $paymentDate ?? now()->toDateString(),
        ];
        $genericData->syncDataArray();
        return $genericData;
    }

    /**
     * Assert membership exists with specific dates
     */
    protected function assertMembershipExists(int $customerId, int $planId, string $startDate, string $endDate, string $status = 'active'): void
    {
        $membership = CustomerMembership::where('customer_id', $customerId)
            ->where('membership_plan_id', $planId)
            ->where('status', $status)
            ->first();

        $this->assertNotNull($membership, "Membership should exist for customer {$customerId} with plan {$planId}");
        $this->assertEquals($startDate, $membership->membership_start_date->toDateString(), "Start date should be {$startDate}");
        $this->assertEquals($endDate, $membership->membership_end_date->toDateString(), "End date should be {$endDate}");
    }

    /**
     * Assert bill exists with specific properties
     */
    protected function assertBillExists(int $customerId, string $billType, float $netAmount, float $paidAmount = 0, ?string $billDate = null): CustomerBill
    {
        $query = CustomerBill::where('customer_id', $customerId)
            ->where('bill_type', $billType);

        if ($billDate) {
            $query->whereDate('bill_date', $billDate);
        }

        $bill = $query->first();

        $this->assertNotNull($bill, "Bill should exist for customer {$customerId} with type {$billType}");
        $this->assertEquals($netAmount, (float) $bill->net_amount, "Net amount should be {$netAmount}");
        $this->assertEquals($paidAmount, (float) $bill->paid_amount, "Paid amount should be {$paidAmount}");

        return $bill;
    }
}
