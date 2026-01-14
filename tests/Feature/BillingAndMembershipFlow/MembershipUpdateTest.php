<?php

namespace Tests\Feature\BillingAndMembershipFlow;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use App\Services\Core\CustomerService;
use Carbon\Carbon;

class MembershipUpdateTest extends BillingAndMembershipFlowTestCase
{
    /**
     * Test that updating membership deactivates old membership
     */
    public function test_updating_membership_deactivates_old_membership(): void
    {
        // Create initial membership
        $initialStartDate = Carbon::now()->subMonth();
        $initialEndDate = Carbon::now()->addDays(10);
        $oldMembership = CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $initialStartDate,
            'membership_end_date' => $initialEndDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Update membership to a new plan
        $customerService = app(CustomerService::class);
        $genericData = $this->createBillGenericData([
            'membershipPlanId' => $this->quarterlyPlan->id,
            'membershipStartDate' => Carbon::now()->toDateString(),
        ]);

        $newMembership = $customerService->createOrUpdateMembership($this->customer->id, $genericData);

        // Assert old membership was deactivated
        $oldMembership->refresh();
        $this->assertEquals(CustomerMembershipConstant::STATUS_DEACTIVATED, $oldMembership->status, 'Old membership should be deactivated');

        // Assert new membership was created
        $this->assertNotNull($newMembership);
        $this->assertEquals($this->quarterlyPlan->id, $newMembership->membership_plan_id);
        $this->assertEquals(CustomerMembershipConstant::STATUS_ACTIVE, $newMembership->status);
    }

    /**
     * Test that updating membership voids old bills with outstanding balance
     */
    public function test_updating_membership_voids_old_bills_with_outstanding_balance(): void
    {
        // Create initial membership
        $initialStartDate = Carbon::now()->subMonth();
        $initialEndDate = Carbon::now()->addDays(10);
        $oldMembership = CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $initialStartDate,
            'membership_end_date' => $initialEndDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Create unpaid bill for old membership
        $oldBill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => $initialStartDate,
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 0,
            'bill_status' => CustomerBillConstant::BILL_STATUS_ACTIVE,
        ]);

        // Create partially paid bill for old membership
        $partialBill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => $initialStartDate->copy()->addDays(5),
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 500.00,
            'bill_status' => CustomerBillConstant::BILL_STATUS_PARTIAL,
        ]);

        // Recalculate balance
        $this->customer->recalculateBalance();

        // Update membership to a new plan
        $customerService = app(CustomerService::class);
        $genericData = $this->createBillGenericData([
            'membershipPlanId' => $this->quarterlyPlan->id,
            'membershipStartDate' => Carbon::now()->toDateString(),
        ]);

        $customerService->createOrUpdateMembership($this->customer->id, $genericData);

        // Assert old bills were voided (status changed, amounts preserved)
        $oldBill->refresh();
        $partialBill->refresh();

        $this->assertEquals(CustomerBillConstant::BILL_STATUS_VOIDED, $oldBill->bill_status, 'Unpaid bill should be voided');
        $this->assertEquals(1000.00, (float) $oldBill->net_amount, 'Unpaid bill net_amount should be preserved');

        $this->assertEquals(CustomerBillConstant::BILL_STATUS_VOIDED, $partialBill->bill_status, 'Partially paid bill should be voided');
        $this->assertEquals(1000.00, (float) $partialBill->net_amount, 'Partially paid bill net_amount should be preserved');
    }

    /**
     * Test that updating membership creates automated bill for new membership
     */
    public function test_updating_membership_creates_automated_bill_for_new_membership(): void
    {
        // Create initial membership
        $initialStartDate = Carbon::now()->subMonth();
        $initialEndDate = Carbon::now()->addDays(10);
        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $initialStartDate,
            'membership_end_date' => $initialEndDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Update membership to a new plan
        $newStartDate = Carbon::now()->toDateString();
        $customerService = app(CustomerService::class);
        $genericData = $this->createBillGenericData([
            'membershipPlanId' => $this->quarterlyPlan->id,
            'membershipStartDate' => $newStartDate,
        ]);

        $newMembership = $customerService->createOrUpdateMembership($this->customer->id, $genericData);

        // Assert automated bill was created for new membership
        $newBill = CustomerBill::where('customer_id', $this->customer->id)
            ->where('membership_plan_id', $this->quarterlyPlan->id)
            ->where('bill_type', CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION)
            ->whereDate('bill_date', $newStartDate)
            ->first();

        $this->assertNotNull($newBill, 'Automated bill should be created for new membership');
        $this->assertEquals($this->quarterlyPlan->price, (float) $newBill->gross_amount);
        $this->assertEquals(CustomerBillConstant::BILL_STATUS_ACTIVE, $newBill->bill_status);
    }

    /**
     * Test that updating membership recalculates customer balance (excluding voided bills)
     */
    public function test_updating_membership_recalculates_balance_excluding_voided_bills(): void
    {
        // Create initial membership
        $initialStartDate = Carbon::now()->subMonth();
        $initialEndDate = Carbon::now()->addDays(10);
        CustomerMembership::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'membership_start_date' => $initialStartDate,
            'membership_end_date' => $initialEndDate,
            'status' => CustomerMembershipConstant::STATUS_ACTIVE,
        ]);

        // Create unpaid bill for old membership
        $oldBill = CustomerBill::create([
            'account_id' => 1,
            'customer_id' => $this->customer->id,
            'membership_plan_id' => $this->monthlyPlan->id,
            'bill_type' => CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION,
            'bill_date' => $initialStartDate,
            'gross_amount' => 1000.00,
            'discount_percentage' => 0,
            'net_amount' => 1000.00,
            'paid_amount' => 0,
            'bill_status' => CustomerBillConstant::BILL_STATUS_ACTIVE,
        ]);

        // Recalculate initial balance
        $this->customer->recalculateBalance();
        $this->customer->refresh();
        $initialBalance = (float) $this->customer->balance;
        $this->assertEquals(1000.00, $initialBalance, 'Initial balance should include unpaid bill');

        // Update membership (this will void the old bill)
        $customerService = app(CustomerService::class);
        $genericData = $this->createBillGenericData([
            'membershipPlanId' => $this->quarterlyPlan->id,
            'membershipStartDate' => Carbon::now()->toDateString(),
        ]);

        $customerService->createOrUpdateMembership($this->customer->id, $genericData);

        // Recalculate balance after update
        $this->customer->refresh();
        $this->customer->recalculateBalance();
        $this->customer->refresh();

        // Balance should only include new bill, not voided old bill
        // New bill: quarterlyPlan->price (e.g., 2000.00) - 0 paid = 2000.00
        // Old bill: voided, so excluded from calculation
        $expectedBalance = (float) $this->quarterlyPlan->price;
        $this->assertEquals($expectedBalance, (float) $this->customer->balance, 'Balance should exclude voided bills and include new bill');
    }

    /**
     * Test that creating new membership (no old membership) still creates automated bill
     */
    public function test_creating_new_membership_creates_automated_bill(): void
    {
        // Ensure customer has no existing membership
        $this->assertNull($this->customer->currentMembership, 'Customer should have no membership initially');

        // Create new membership
        $newStartDate = Carbon::now()->toDateString();
        $customerService = app(CustomerService::class);
        $genericData = $this->createBillGenericData([
            'membershipPlanId' => $this->monthlyPlan->id,
            'membershipStartDate' => $newStartDate,
        ]);

        $newMembership = $customerService->createOrUpdateMembership($this->customer->id, $genericData);

        // Assert membership was created
        $this->assertNotNull($newMembership);
        $this->assertEquals(CustomerMembershipConstant::STATUS_ACTIVE, $newMembership->status);

        // Assert automated bill was created
        $newBill = CustomerBill::where('customer_id', $this->customer->id)
            ->where('membership_plan_id', $this->monthlyPlan->id)
            ->where('bill_type', CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION)
            ->whereDate('bill_date', $newStartDate)
            ->first();

        $this->assertNotNull($newBill, 'Automated bill should be created for new membership');
        $this->assertEquals($this->monthlyPlan->price, (float) $newBill->gross_amount);
    }
}
