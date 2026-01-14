<?php

namespace Tests\Feature\BillingAndMembershipFlow;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Console\Commands\MembershipPlanChecker;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use App\Repositories\Core\CustomerBillRepository;
use Carbon\Carbon;

class MembershipExpirationTest extends BillingAndMembershipFlowTestCase
{
    /**
     * Test that membership expires if automated bill is not paid
     */
    public function test_membership_expires_if_automated_bill_not_paid(): void
    {
        // Create expired membership
        $endDate = Carbon::now()->subDay();
        $membership = CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $endDate->copy()->subMonth(),
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Create automated bill (unpaid)
        $billRepository = app(CustomerBillRepository::class);
        $bill = $billRepository->createAutomatedBill(
            1,
            $this->customer->id,
            $this->monthlyPlan->id,
            $this->monthlyPlan->price,
            $endDate
        );

        // Run expiration checker
        $command = new MembershipPlanChecker($billRepository);
        $command->handle();

        // Assert membership was expired
        $membership->refresh();
        $this->assertEquals(CustomerMembershipConstant::STATUS_EXPIRED, $membership->status, 'Membership should be expired if bill not paid');
    }

    /**
     * Test that membership does NOT expire if automated bill is paid
     */
    public function test_membership_does_not_expire_if_automated_bill_paid(): void
    {
        // Create expired membership
        $endDate = Carbon::now()->subDay();
        $membership = CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $endDate->copy()->subMonth(),
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Create automated bill and pay it
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

        // Run expiration checker
        $command = new MembershipPlanChecker($billRepository);
        $command->handle();

        // Assert membership was NOT expired (should be extended by payment)
        $membership->refresh();
        $this->assertEquals(CustomerMembershipConstant::STATUS_ACTIVE, $membership->status, 'Membership should not expire if bill is paid');
    }

    /**
     * Test that membership does NOT expire if automated bill has partial payment
     */
    public function test_membership_does_not_expire_if_automated_bill_partially_paid(): void
    {
        // Create expired membership
        $endDate = Carbon::now()->subDay();
        $membership = CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $endDate->copy()->subMonth(),
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Create automated bill and make partial payment
        $billRepository = app(CustomerBillRepository::class);
        $bill = $billRepository->createAutomatedBill(
            1,
            $this->customer->id,
            $this->monthlyPlan->id,
            $this->monthlyPlan->price,
            $endDate
        );

        // Make partial payment
        $paymentData = $this->createPaymentGenericData($bill->id, 500.00);
        $this->paymentService->addPayment($paymentData);

        // Run expiration checker
        $command = new MembershipPlanChecker($billRepository);
        $command->handle();

        // Assert membership was NOT expired (partial payment prevents expiration)
        $membership->refresh();
        $this->assertEquals(CustomerMembershipConstant::STATUS_ACTIVE, $membership->status, 'Membership should not expire if bill has partial payment');
    }
}
