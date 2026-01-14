<?php

namespace Tests\Feature\BillingAndMembershipFlow;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use Carbon\Carbon;

class CurrentPeriodBillTest extends BillingAndMembershipFlowTestCase
{
    /**
     * Test that creating a bill for current/expired period
     * immediately creates/updates membership
     */
    public function test_current_period_bill_creates_membership_immediately(): void
    {
        // Create existing membership that's about to expire
        $startDate = Carbon::now()->subDays(20);
        $endDate = Carbon::now()->subDays(5); // Expired 5 days ago

        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $startDate,
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_EXPIRED,
        ]);

        // Create bill for current/expired period
        $billDate = Carbon::now()->toDateString();
        $genericData = $this->createBillGenericData([
            'billType' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'membershipPlanId' => $this->monthlyPlan->id,
            'billDate' => $billDate,
            'grossAmount' => 1000.00,
            'netAmount' => 1000.00,
        ]);

        $bill = $this->billService->create($genericData);

        // Assert membership was created/updated
        $membership = CustomerMembership::where('customer_id', $this->customer->id)
            ->where('status', 'active')
            ->latest('membership_start_date')
            ->first();

        $this->assertNotNull($membership, 'Membership should be created/updated for current period bill');
        $this->assertEquals($billDate, $membership->membership_start_date->toDateString());
    }

    /**
     * Test that payment on current period bill doesn't change membership
     */
    public function test_payment_on_current_period_bill_does_not_change_membership(): void
    {
        // Create membership and bill
        $billDate = Carbon::now()->toDateString();
        $genericData = $this->createBillGenericData([
            'billType' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'membershipPlanId' => $this->monthlyPlan->id,
            'billDate' => $billDate,
            'grossAmount' => 1000.00,
            'netAmount' => 1000.00,
        ]);

        $bill = $this->billService->create($genericData);
        $membership = CustomerMembership::where('customer_id', $this->customer->id)->first();
        $originalEndDate = $membership->membership_end_date;

        // Make payment
        $paymentData = $this->createPaymentGenericData($bill->id, 1000.00);
        $this->paymentService->addPayment($paymentData);

        // Assert membership dates didn't change
        $membership->refresh();
        $this->assertEquals($originalEndDate->toDateString(), $membership->membership_end_date->toDateString());
    }
}
