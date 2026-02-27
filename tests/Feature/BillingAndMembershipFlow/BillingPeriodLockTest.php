<?php

namespace Tests\Feature\BillingAndMembershipFlow;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use Carbon\Carbon;

class BillingPeriodLockTest extends BillingAndMembershipFlowTestCase
{
    public function test_previous_period_voided_bill_cannot_receive_payment_after_reactivation(): void
    {
        $expiredStartDate = Carbon::parse('2026-01-01');
        $expiredEndDate = Carbon::parse('2026-02-01');

        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $expiredStartDate,
            'membership_end_date' => $expiredEndDate,
            'status' => CustomerMembershipConstant::STATUS_EXPIRED,
        ]);

        $oldBill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => $expiredStartDate,
            'billing_period' => $expiredStartDate->format('mdY'),
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 0,
            'bill_status' => CustomerBillConstant::BILL_STATUS_ACTIVE,
        ]);

        $reactivationBillData = $this->createBillGenericData([
            'billType' => CustomerBillConstant::BILL_TYPE_REACTIVATION_FEE,
            'grossAmount' => 500.00,
            'netAmount' => 500.00,
            'billDate' => '2026-02-25',
        ]);
        $reactivationBill = $this->billService->create($reactivationBillData);

        $reactivationPayment = $this->createPaymentGenericData($reactivationBill->id, 500.00, '2026-02-25');
        $this->paymentService->addPayment($reactivationPayment);

        $oldBill->refresh();
        $this->assertEquals(CustomerBillConstant::BILL_STATUS_VOIDED, $oldBill->bill_status);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot add payment to a voided bill.');

        $paymentData = $this->createPaymentGenericData($oldBill->id, 100.00, '2026-02-26');
        $this->paymentService->addPayment($paymentData);
    }

    public function test_previous_billing_period_paid_bill_cannot_be_updated_after_new_cycle_starts(): void
    {
        $oldStartDate = Carbon::parse('2026-01-25');
        $oldEndDate = Carbon::parse('2026-02-25');

        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $oldStartDate,
            'membership_end_date' => $oldEndDate,
            'status' => CustomerMembershipConstant::STATUS_EXPIRED,
        ]);

        $oldPaidBill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => $oldStartDate,
            'billing_period' => $oldStartDate->format('mdY'),
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 1000.00,
            'bill_status' => CustomerBillConstant::BILL_STATUS_PAID,
        ]);

        $reactivationBillData = $this->createBillGenericData([
            'billType' => CustomerBillConstant::BILL_TYPE_REACTIVATION_FEE,
            'grossAmount' => 500.00,
            'netAmount' => 500.00,
            'billDate' => '2026-02-25',
        ]);
        $reactivationBill = $this->billService->create($reactivationBillData);

        $reactivationPayment = $this->createPaymentGenericData($reactivationBill->id, 500.00, '2026-02-25');
        $this->paymentService->addPayment($reactivationPayment);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot update a bill from a previous billing period.');

        $updateData = $this->createBillGenericData([
            'customerId' => $this->customer->id,
            'billType' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'membershipPlanId' => $this->monthlyPlan->id,
            'billDate' => $oldStartDate->toDateString(),
            'grossAmount' => 900.00,
            'netAmount' => 900.00,
            'discountPercentage' => 0,
            'paidAmount' => 900.00,
            'billStatus' => CustomerBillConstant::BILL_STATUS_PAID,
        ]);

        $this->billService->updateBill($oldPaidBill->id, $updateData);
    }
}

