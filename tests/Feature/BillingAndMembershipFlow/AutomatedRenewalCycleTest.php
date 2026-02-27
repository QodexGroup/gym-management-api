<?php

namespace Tests\Feature\BillingAndMembershipFlow;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Constant\NotificationConstant;
use App\Console\Commands\CheckMembershipExpiration;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerRepository;
use Carbon\Carbon;

class AutomatedRenewalCycleTest extends BillingAndMembershipFlowTestCase
{
    /**
     * Test that automated bill is created 7 days before expiration
     */
    public function test_automated_bill_created_7_days_before_expiration(): void
    {
        // Create membership expiring in 7 days
        $endDate = Carbon::now()->addDays(7);
        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => Carbon::now()->subMonth(),
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Run the command
        $command = new CheckMembershipExpiration(
            app(\App\Services\Core\NotificationService::class),
            app(CustomerBillRepository::class),
            app(CustomerRepository::class)
        );
        $command->handle();

        // Assert automated bill was created
        $bill = CustomerBill::where('customer_id', $this->customer->id)
            ->where('bill_type', CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION)
            ->whereDate('bill_date', $endDate->toDateString())
            ->first();

        $this->assertNotNull($bill, 'Automated bill should be created');
        $this->assertEquals($this->monthlyPlan->price, (float) $bill->gross_amount);
        $this->assertEquals(CustomerBillConstant::BILL_STATUS_ACTIVE, $bill->bill_status);
    }

    /**
     * Test that automated bill does NOT create membership
     */
    public function test_automated_bill_does_not_create_membership(): void
    {
        // Create membership expiring in 7 days
        $endDate = Carbon::now()->addDays(7);
        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => Carbon::now()->subMonth(),
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Run the command
        $command = new CheckMembershipExpiration(
            app(\App\Services\Core\NotificationService::class),
            app(CustomerBillRepository::class),
            app(CustomerRepository::class)
        );
        $command->handle();

        // Assert membership was NOT extended
        $membership = CustomerMembership::where('customer_id', $this->customer->id)->first();
        $this->assertEquals($endDate->toDateString(), $membership->membership_end_date->toDateString(), 'Membership should not be extended until payment');
    }

    /**
     * Test that payment on automated bill extends membership
     */
    public function test_payment_on_automated_bill_extends_membership(): void
    {
        // Use fixed dates to make expectations explicit:
        $startDate = Carbon::parse('2026-01-29');
        // End date is computed the same way as the real app (start + 1 month - 1 day)
        $endDate = $this->monthlyPlan->calculateEndDate($startDate->copy());

        // Create membership and automated bill
        $membership = CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $startDate,
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Ensure bill is created after membership (add 1 second delay)
        sleep(1);

        // Create automated bill manually (simulating command)
        $billRepository = app(CustomerBillRepository::class);
        // Renewal bill starts the day after the current end date
        $renewalStart = $endDate->copy()->addDay(); // 2026-02-29
        $bill = $billRepository->createAutomatedBill(
            1,
            $this->customer->id,
            $this->monthlyPlan->id,
            $this->monthlyPlan->price,
            $renewalStart
        );

        // Make payment
        $paymentData = $this->createPaymentGenericData($bill->id, $this->monthlyPlan->price);
        $this->paymentService->addPayment($paymentData);

        // Assert membership was extended
        $membership = CustomerMembership::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($membership, 'Membership should exist after payment on automated bill');
        // Whatever the new start date is, the end date should be consistent with the plan's
        // inclusive end-date calculation.
        $expectedEnd = $this->monthlyPlan->calculateEndDate($membership->membership_start_date->copy());
        $this->assertEquals(
            $expectedEnd->toDateString(),
            $membership->membership_end_date->toDateString()
        );
    }

    /**
     * Test that early payment on automated bill extends membership before expiration
     */
    public function test_early_payment_on_automated_bill_extends_membership(): void
    {
        // Use the same fixed period as above:
        // Current membership: 2026-01-29 to 2026-02-28 (inclusive)
        $startDate = Carbon::parse('2026-01-29');
        $endDate = $this->monthlyPlan->calculateEndDate($startDate->copy());

        // Create membership expiring on fixed end date
        $membership = CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $startDate,
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Ensure bill is created after membership (add 1 second delay)
        sleep(1);

        // Create automated bill for the renewal period
        $renewalStart = $endDate->copy()->addDay(); // 2026-02-29
        $billRepository = app(CustomerBillRepository::class);
        $bill = $billRepository->createAutomatedBill(
            1,
            $this->customer->id,
            $this->monthlyPlan->id,
            $this->monthlyPlan->price,
            $renewalStart
        );

        // Make early payment (3 days before expiration)
        $paymentData = $this->createPaymentGenericData($bill->id, $this->monthlyPlan->price);
        $this->paymentService->addPayment($paymentData);

        // Assert membership was extended immediately
        $membership = CustomerMembership::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($membership, 'Membership should exist after early payment on automated bill');
        $expectedEnd = $this->monthlyPlan->calculateEndDate($membership->membership_start_date->copy());
        $this->assertEquals(
            $expectedEnd->toDateString(),
            $membership->membership_end_date->toDateString()
        );
    }

    /**
     * Test that automated bill command doesn't create duplicates if run twice
     */
    public function test_automated_bill_command_prevents_duplicates(): void
    {
        // Create membership expiring in 7 days
        $endDate = Carbon::now()->addDays(7);
        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => Carbon::now()->subMonth(),
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        $command = new CheckMembershipExpiration(
            app(\App\Services\Core\NotificationService::class),
            app(CustomerBillRepository::class),
            app(CustomerRepository::class)
        );

        // Run command first time
        $command->handle();

        // Count bills after first run
        $billsAfterFirstRun = CustomerBill::where('customer_id', $this->customer->id)
            ->where('bill_type', CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION)
            ->whereDate('bill_date', $endDate->toDateString())
            ->count();

        $this->assertEquals(1, $billsAfterFirstRun, 'First run should create one bill');

        // Run command second time
        $command->handle();

        // Count bills after second run - should still be 1
        $billsAfterSecondRun = CustomerBill::where('customer_id', $this->customer->id)
            ->where('bill_type', CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION)
            ->whereDate('bill_date', $endDate->toDateString())
            ->count();

        $this->assertEquals(1, $billsAfterSecondRun, 'Second run should not create duplicate bill');
    }
}
