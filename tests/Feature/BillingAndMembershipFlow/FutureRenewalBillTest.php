<?php

namespace Tests\Feature\BillingAndMembershipFlow;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use Carbon\Carbon;

class FutureRenewalBillTest extends BillingAndMembershipFlowTestCase
{
    /**
     * Test that creating a bill for future period does NOT create membership
     * (waits for payment)
     */
    public function test_future_renewal_bill_does_not_create_membership(): void
    {
        // Create existing active membership
        $startDate = Carbon::now();
        $endDate = Carbon::now()->addMonth();

        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $startDate,
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Create bill for future period (after current membership ends)
        $futureBillDate = $endDate->copy()->addDay()->toDateString();
        $genericData = $this->createBillGenericData([
            'billType' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'membershipPlanId' => $this->monthlyPlan->id,
            'billDate' => $futureBillDate,
            'grossAmount' => 1000.00,
            'netAmount' => 1000.00,
        ]);

        $bill = $this->billService->create($genericData);

        // Assert bill was created
        $this->assertInstanceOf(CustomerBill::class, $bill);

        // Assert membership was NOT extended (still ends at original date)
        $membership = CustomerMembership::where('customer_id', $this->customer->id)
            ->where('status', 'active')
            ->first();

        $this->assertNotNull($membership);
        $this->assertEquals($endDate->toDateString(), $membership->membership_end_date->toDateString(), 'Membership should not be extended until payment');
    }

    /**
     * Test that payment on future renewal bill extends membership
     */
    public function test_payment_on_future_renewal_bill_extends_membership(): void
    {
        // Create existing active membership
        $startDate = Carbon::now();
        $endDate = Carbon::now()->addMonth();

        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $startDate,
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Create bill for future period
        $futureBillDate = $endDate->copy()->toDateString();
        $genericData = $this->createBillGenericData([
            'billType' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'membershipPlanId' => $this->monthlyPlan->id,
            'billDate' => $futureBillDate,
            'grossAmount' => 1000.00,
            'netAmount' => 1000.00,
        ]);

        $bill = $this->billService->create($genericData);

        // Make payment
        $paymentData = $this->createPaymentGenericData($bill->id, 1000.00);
        $this->paymentService->addPayment($paymentData);

        // Assert membership was extended
        $membership = CustomerMembership::where('customer_id', $this->customer->id)
            ->where('status', 'active')
            ->first();

        $this->assertNotNull($membership);
        $this->assertEquals($futureBillDate, $membership->membership_start_date->toDateString(), 'Membership should start from bill date');
        $this->assertEquals(Carbon::parse($futureBillDate)->addMonth()->toDateString(), $membership->membership_end_date->toDateString(), 'Membership should be extended by plan period');
    }

    /**
     * Test that partial payment on future renewal bill extends membership
     */
    public function test_partial_payment_on_future_renewal_bill_extends_membership(): void
    {
        // Create existing active membership
        $startDate = Carbon::now();
        $endDate = Carbon::now()->addMonth();

        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $startDate,
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Create bill for future period
        $futureBillDate = $endDate->copy()->toDateString();
        $genericData = $this->createBillGenericData([
            'billType' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'membershipPlanId' => $this->monthlyPlan->id,
            'billDate' => $futureBillDate,
            'grossAmount' => 1000.00,
            'netAmount' => 1000.00,
        ]);

        $bill = $this->billService->create($genericData);

        // Make partial payment
        $paymentData = $this->createPaymentGenericData($bill->id, 500.00);
        $this->paymentService->addPayment($paymentData);

        // Assert membership was extended even with partial payment
        $membership = CustomerMembership::where('customer_id', $this->customer->id)
            ->where('status', 'active')
            ->first();

        $this->assertNotNull($membership);
        $this->assertEquals($futureBillDate, $membership->membership_start_date->toDateString(), 'Membership should be extended even with partial payment');
    }
}
