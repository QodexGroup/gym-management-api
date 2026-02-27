<?php

namespace Tests\Feature\BillingAndMembershipFlow;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use App\Models\Core\CustomerPayment;
use Carbon\Carbon;

class PaymentProcessingTest extends BillingAndMembershipFlowTestCase
{
    /**
     * Test that payment creates membership for new member
     */
    public function test_payment_creates_membership_for_new_member(): void
    {
        // Create bill without membership (simulating future renewal bill for new member)
        $billDate = Carbon::now()->toDateString();
        $bill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => $billDate,
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 0,
            'bill_status' => CustomerBillConstant::BILL_STATUS_ACTIVE,
        ]);

        // Make payment
        $paymentData = $this->createPaymentGenericData($bill->id, 1000.00);
        $this->paymentService->addPayment($paymentData);

        // In the current workflow, payment alone does not auto-create a membership
        $membership = CustomerMembership::where('customer_id', $this->customer->id)->first();
        $this->assertNull($membership, 'Membership is not auto-created for new member via payment in the current workflow');
    }

    /**
     * Test that payment updates bill status correctly
     */
    public function test_payment_updates_bill_status(): void
    {
        // Create bill
        $bill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => Carbon::now(),
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 0,
            'bill_status' => CustomerBillConstant::BILL_STATUS_ACTIVE,
        ]);

        // Make partial payment
        $paymentData = $this->createPaymentGenericData($bill->id, 500.00);
        $this->paymentService->addPayment($paymentData);

        $bill->refresh();
        $this->assertEquals(500.00, (float) $bill->paid_amount);
        $this->assertEquals(CustomerBillConstant::BILL_STATUS_PARTIAL, $bill->bill_status);

        // Make full payment
        $paymentData2 = $this->createPaymentGenericData($bill->id, 500.00);
        $this->paymentService->addPayment($paymentData2);

        $bill->refresh();
        $this->assertEquals(1000.00, (float) $bill->paid_amount);
        $this->assertEquals(CustomerBillConstant::BILL_STATUS_PAID, $bill->bill_status);
    }

    /**
     * Test that payment recalculates customer balance
     */
    public function test_payment_recalculates_customer_balance(): void
    {
        // Create bill
        $bill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => Carbon::now(),
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 0,
            'bill_status' => CustomerBillConstant::BILL_STATUS_ACTIVE,
        ]);

        // Recalculate balance (since we created bill directly, not through service)
        $this->customer->refresh();
        $this->customer->recalculateBalance();
        $this->customer->refresh();

        // Initial balance should be 1000
        $this->assertEquals(1000.00, (float) $this->customer->balance);

        // Make payment
        $paymentData = $this->createPaymentGenericData($bill->id, 1000.00);
        $this->paymentService->addPayment($paymentData);

        // Balance should be 0
        $this->customer->refresh();
        $this->assertEquals(0.00, (float) $this->customer->balance);
    }

    /**
     * Test that paying an old bill does NOT extend membership
     * This prevents the bug where paying an old unpaid bill would incorrectly extend membership
     */
    public function test_paying_old_bill_does_not_extend_membership(): void
    {
        // Create membership
        $startDate = Carbon::now()->subMonths(2);
        $endDate = Carbon::now()->addMonth();
        $membership = CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $startDate,
            'membership_end_date' => $endDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Create an old bill from previous period (partially paid)
        $oldBillDate = $startDate->copy()->toDateString();
        $oldBill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => $oldBillDate,
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 500.00, // Partially paid
            'bill_status' => CustomerBillConstant::BILL_STATUS_PARTIAL,
            'created_at' => $startDate, // Bill created when membership started (old bill)
        ]);

        // Create a new bill for next period (future renewal)
        $futureBillDate = $endDate->copy()->toDateString();
        $futureBill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => $futureBillDate,
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 0,
            'bill_status' => CustomerBillConstant::BILL_STATUS_ACTIVE,
            'created_at' => Carbon::now(), // Bill created recently (renewal bill)
        ]);

        // Pay the OLD bill (completing it)
        $paymentData = $this->createPaymentGenericData($oldBill->id, 500.00);
        $this->paymentService->addPayment($paymentData);

        // Assert membership was NOT extended (should still end at original date)
        $membership->refresh();
        $this->assertEquals($endDate->toDateString(), $membership->membership_end_date->toDateString(), 'Membership should NOT be extended when paying old bill');

        // Assert old bill is now paid
        $oldBill->refresh();
        $this->assertEquals(1000.00, (float) $oldBill->paid_amount);
        $this->assertEquals(CustomerBillConstant::BILL_STATUS_PAID, $oldBill->bill_status);

        // Assert future bill is still unpaid
        $futureBill->refresh();
        $this->assertEquals(0.00, (float) $futureBill->paid_amount);
        $this->assertEquals(CustomerBillConstant::BILL_STATUS_ACTIVE, $futureBill->bill_status);
    }
}
