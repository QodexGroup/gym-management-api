<?php

namespace Tests\Feature\BillingAndMembershipFlow;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Constant\NotificationConstant;
use App\Console\Commands\CheckMembershipExpiration;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use App\Repositories\Core\CustomerBillRepository;
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
            app(CustomerBillRepository::class)
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
            app(CustomerBillRepository::class)
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
        // Create membership and automated bill
        $endDate = Carbon::now()->addDays(7);
        $membership = CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => Carbon::now()->subMonth(),
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Ensure bill is created after membership (add 1 second delay)
        sleep(1);

        // Create automated bill manually (simulating command)
        $billRepository = app(CustomerBillRepository::class);
        $bill = $billRepository->createAutomatedBill(
            1,
            $this->customer->id,
            $this->monthlyPlan->id,
            $this->monthlyPlan->price,
            $endDate
        );

        // Make payment
        $paymentData = $this->createPaymentGenericData($bill->id, $this->monthlyPlan->price);
        $this->paymentService->addPayment($paymentData);

        // Assert membership was extended
        $membership = CustomerMembership::where('customer_id', $this->customer->id)->first();
        $this->assertEquals($endDate->toDateString(), $membership->membership_start_date->toDateString());
        $this->assertEquals($endDate->copy()->addMonth()->toDateString(), $membership->membership_end_date->toDateString());
    }

    /**
     * Test that early payment on automated bill extends membership before expiration
     */
    public function test_early_payment_on_automated_bill_extends_membership(): void
    {
        // Create membership expiring in 7 days
        $endDate = Carbon::now()->addDays(7);
        $membership = CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => Carbon::now()->subMonth(),
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Ensure bill is created after membership (add 1 second delay)
        sleep(1);

        // Create automated bill
        $billRepository = app(CustomerBillRepository::class);
        $bill = $billRepository->createAutomatedBill(
            1,
            $this->customer->id,
            $this->monthlyPlan->id,
            $this->monthlyPlan->price,
            $endDate
        );

        // Make early payment (3 days before expiration)
        $paymentData = $this->createPaymentGenericData($bill->id, $this->monthlyPlan->price);
        $this->paymentService->addPayment($paymentData);

        // Assert membership was extended immediately
        $membership = CustomerMembership::where('customer_id', $this->customer->id)->first();
        $this->assertEquals($endDate->toDateString(), $membership->membership_start_date->toDateString());
        $this->assertEquals($endDate->copy()->addMonth()->toDateString(), $membership->membership_end_date->toDateString());
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
            app(CustomerBillRepository::class)
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
