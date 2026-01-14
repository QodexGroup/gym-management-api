<?php

namespace Tests\Feature\BillingAndMembershipFlow;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use Carbon\Carbon;

class ReactivationFeeTest extends BillingAndMembershipFlowTestCase
{
    /**
     * Test that reactivation fee bill voids expired membership balances
     */
    public function test_reactivation_fee_voids_expired_membership_bills(): void
    {
        // Create expired membership
        $expiredEndDate = Carbon::now()->subDays(10);
        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $expiredEndDate->copy()->subMonth(),
            'membership_end_date' => $expiredEndDate,
            'status' => CustomerMembershipConstant::STATUS_EXPIRED,
        ]);

        // Create unpaid bill for expired membership
        $expiredBill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => $expiredEndDate->copy()->subMonth(),
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 0,
            'bill_status' => CustomerBillConstant::BILL_STATUS_ACTIVE,
        ]);

        // Create reactivation fee bill
        $genericData = $this->createBillGenericData([
            'billType' => CustomerBillConstant::BILL_TYPE_REACTIVATION_FEE,
            'grossAmount' => 500.00,
            'netAmount' => 500.00,
        ]);

        $reactivationBill = $this->billService->create($genericData);

        // Assert expired bill was voided (status changed, amounts preserved)
        $expiredBill->refresh();
        $this->assertEquals(1000.00, (float) $expiredBill->net_amount, 'Expired bill net_amount should be preserved');
        $this->assertEquals(CustomerBillConstant::BILL_STATUS_VOIDED, $expiredBill->bill_status, 'Expired bill should be marked as voided');
    }

    /**
     * Test that reactivation fee payment creates membership with free month
     */
    public function test_reactivation_fee_payment_creates_membership_with_free_month(): void
    {
        // Create expired membership
        $expiredEndDate = Carbon::now()->subDays(10);
        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $expiredEndDate->copy()->subMonth(),
            'membership_end_date' => $expiredEndDate,
            'status' => CustomerMembershipConstant::STATUS_EXPIRED,
        ]);

        // Create reactivation fee bill
        $genericData = $this->createBillGenericData([
            'billType' => CustomerBillConstant::BILL_TYPE_REACTIVATION_FEE,
            'grossAmount' => 500.00,
            'netAmount' => 500.00,
        ]);

        $reactivationBill = $this->billService->create($genericData);

        // Make payment
        $paymentDate = Carbon::now()->toDateString();
        $paymentData = $this->createPaymentGenericData($reactivationBill->id, 500.00, $paymentDate);
        $this->paymentService->addPayment($paymentData);

        // Assert new membership was created with free month
        $membership = CustomerMembership::where('customer_id', $this->customer->id)
            ->where('status', 'active')
            ->latest('membership_start_date')
            ->first();

        $this->assertNotNull($membership, 'New membership should be created after reactivation fee payment');
        $this->assertEquals($this->monthlyPlan->id, $membership->membership_plan_id, 'Should use same plan as expired membership');
        $this->assertEquals($paymentDate, $membership->membership_start_date->toDateString(), 'Start date should be payment date');
        $this->assertEquals(Carbon::parse($paymentDate)->addMonth()->toDateString(), $membership->membership_end_date->toDateString(), 'Should have 1 free month');
    }

    /**
     * Test that reactivation fee works with quarterly plan (still gives 1 month free)
     */
    public function test_reactivation_fee_with_quarterly_plan_gives_one_month_free(): void
    {
        // Create expired quarterly membership
        $expiredEndDate = Carbon::now()->subDays(10);
        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->quarterlyPlan->id,
            'membership_start_date' => $expiredEndDate->copy()->subMonths(3),
            'membership_end_date' => $expiredEndDate,
            'status' => CustomerMembershipConstant::STATUS_EXPIRED,
        ]);

        // Create reactivation fee bill
        $genericData = $this->createBillGenericData([
            'billType' => CustomerBillConstant::BILL_TYPE_REACTIVATION_FEE,
            'grossAmount' => 500.00,
            'netAmount' => 500.00,
        ]);

        $reactivationBill = $this->billService->create($genericData);

        // Make payment
        $paymentDate = Carbon::now()->toDateString();
        $paymentData = $this->createPaymentGenericData($reactivationBill->id, 500.00, $paymentDate);
        $this->paymentService->addPayment($paymentData);

        // Assert new membership was created with 1 month free (not 3 months)
        $membership = CustomerMembership::where('customer_id', $this->customer->id)
            ->where('status', 'active')
            ->latest('membership_start_date')
            ->first();

        $this->assertNotNull($membership);
        $this->assertEquals($this->quarterlyPlan->id, $membership->membership_plan_id);
        $this->assertEquals($paymentDate, $membership->membership_start_date->toDateString());
        // Should be 1 month, not 3 months (quarterly period)
        $this->assertEquals(Carbon::parse($paymentDate)->addMonth()->toDateString(), $membership->membership_end_date->toDateString(), 'Should have 1 free month regardless of plan type');
    }
}
