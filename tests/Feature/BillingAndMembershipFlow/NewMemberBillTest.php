<?php

namespace Tests\Feature\BillingAndMembershipFlow;

use App\Constant\CustomerBillConstant;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use Carbon\Carbon;

class NewMemberBillTest extends BillingAndMembershipFlowTestCase
{
    /**
     * Test that creating a membership subscription bill for a new member
     * immediately creates the membership
     */
    public function test_new_member_bill_creates_membership_immediately(): void
    {
        // Ensure customer has no existing membership
        $this->assertNull($this->customer->currentMembership, 'Customer should have no membership initially');

        // Create bill for new member
        $billDate = Carbon::now()->toDateString();
        $genericData = $this->createBillGenericData([
            'billType' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'membershipPlanId' => $this->monthlyPlan->id,
            'billDate' => $billDate,
            'grossAmount' => 1000.00,
            'discountPercentage' => 0,
            'netAmount' => 1000.00,
        ]);

        $bill = $this->billService->create($genericData);

        // Assert bill was created
        $this->assertInstanceOf(CustomerBill::class, $bill);
        $this->assertEquals(CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION, $bill->bill_type);
        $this->assertEquals(1000.00, (float) $bill->net_amount);

        // Assert membership was created immediately
        $membership = CustomerMembership::where('customer_id', $this->customer->id)
            ->where('membership_plan_id', $this->monthlyPlan->id)
            ->where('status', 'active')
            ->first();

        $this->assertNotNull($membership, 'Membership should be created immediately for new member');
        $this->assertEquals($billDate, $membership->membership_start_date->toDateString());
        // End date should follow plan's inclusive end date calculation
        $expectedEnd = $this->monthlyPlan->calculateEndDate(Carbon::parse($billDate));
        $this->assertEquals($expectedEnd->toDateString(), $membership->membership_end_date->toDateString());
    }

    /**
     * Test that payment on new member bill doesn't change membership
     * (since it was already created)
     */
    public function test_payment_on_new_member_bill_does_not_change_membership(): void
    {
        // Create bill and membership
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
        $this->assertEquals($originalEndDate->toDateString(), $membership->membership_end_date->toDateString(), 'Membership end date should not change');
    }
}
