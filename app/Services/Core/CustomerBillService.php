<?php

namespace App\Services\Core;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Helpers\GenericData;
use App\Repositories\Account\MembershipPlanRepository;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerRepository;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerBillService
{
    public function __construct(
        private CustomerBillRepository $customerBillRepository,
        private CustomerRepository $customerRepository,
        private MembershipPlanRepository $membershipPlanRepository
    ) {
    }

    /**
     * @param GenericData $genericData
     *
     * @return CustomerBill
     */
    public function create(GenericData $genericData): CustomerBill
    {
        try {
            return DB::transaction(function () use ($genericData) {
                $data = $genericData->getData();
                $customerId = $data->customerId;
                $accountId = $genericData->userData->account_id;
                $bill = null;

                if($data->billType == CustomerBillConstant::BILL_TYPE_CUSTOM_AMOUNT) {
                    $bill = $this->customerBillRepository->create($genericData);
                }
                elseif($data->billType == CustomerBillConstant::BILL_TYPE_REACTIVATION_FEE) {
                    // Handle reactivation fee - void expired membership balances
                    $this->voidExpiredMembershipBills($customerId, $accountId);
                    $bill = $this->customerBillRepository->create($genericData);
                }
                else{
                    // this is for membership subscription
                    $membershipPlanId = $data->membershipPlanId ?? null;

                    if ($membershipPlanId) {
                        $customer = $this->customerRepository->findCustomerById($customerId, $accountId);
                        $currentMembership = $customer->currentMembership;
                        $billDate = isset($data->billDate)
                            ? Carbon::parse($data->billDate)
                            : Carbon::now();

                        // Only create membership immediately if:
                        // 1. No existing membership (new member), OR
                        // 2. Current membership is expired, OR
                        // 3. Bill is for current/expired period (not a future renewal)
                        $isNewMember = !$currentMembership;
                        $isExpiredMembership = $currentMembership &&
                            $currentMembership->status === CustomerMembershipConstant::STATUS_EXPIRED;
                        $isCurrentPeriod = $currentMembership &&
                            !$isExpiredMembership &&
                            $billDate->lessThanOrEqualTo(Carbon::parse($currentMembership->membership_end_date)->startOfDay());

                        if ($isNewMember || $isExpiredMembership || $isCurrentPeriod) {
                            // Create membership for new members, expired memberships, or current period bills
                            $membershipPlan = $this->membershipPlanRepository->findMembershipPlanById($membershipPlanId, $accountId);
                            $this->customerRepository->createMembership($accountId, $customerId, $membershipPlan, $billDate);
                        }
                        // For future renewal bills: don't create membership yet, wait for payment
                        // (Same behavior as automated bills)
                    }

                    $bill = $this->customerBillRepository->create($genericData);
                }
                $bill->refresh();

                // Recalculate and update customer balance
                $customer = $this->customerRepository->findCustomerById($customerId, $accountId);
                $customer->recalculateBalance();

                // Refresh customer to load updated relationships
                $customer->refresh();
                $customer->load(['currentMembership.membershipPlan', 'currentTrainer']);

                return $bill;
            });
        } catch (\Throwable $th) {
            Log::error('Error creating customer bill', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }


    /**
     * @param int $id
     * @param GenericData $genericData
     *
     * @return CustomerBill
     */
    public function updateBill(int $id, GenericData $genericData): CustomerBill
    {
        try {
            return DB::transaction(function () use ($genericData, $id) {
                $data = $genericData->getData();
                $customerId = $data->customerId;
                $accountId = $genericData->userData->account_id;

                // Get the existing bill to check for membership plan changes
                $existingBill = $this->customerBillRepository->findBillById($id, $accountId);
                $oldMembershipPlanId = $existingBill->membership_plan_id;
                $newMembershipPlanId = $data->membershipPlanId ?? null;

                // Handle membership subscription bill type
                if($data->billType == CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION) {
                    // Check if membership plan changed
                    if ($newMembershipPlanId && $oldMembershipPlanId != $newMembershipPlanId) {
                        // Remove old membership if it exists
                        if ($oldMembershipPlanId) {
                            CustomerMembership::where('customer_id', $customerId)
                                ->where('membership_plan_id', $oldMembershipPlanId)
                                ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
                                ->update(['status' => CustomerMembershipConstant::STATUS_DEACTIVATED]);
                        }

                        // Create new membership only if bill is for current period
                        $customer = $this->customerRepository->findCustomerById($customerId, $accountId);
                        $currentMembership = $customer->currentMembership;
                        $billDate = isset($data->billDate)
                            ? Carbon::parse($data->billDate)
                            : Carbon::now();

                        $isNewMember = !$currentMembership;
                        $isCurrentPeriod = $currentMembership &&
                            $billDate->lessThanOrEqualTo(Carbon::parse($currentMembership->membership_end_date)->startOfDay());

                        if ($isNewMember || $isCurrentPeriod) {
                            $membershipPlan = $this->membershipPlanRepository->findMembershipPlanById($newMembershipPlanId, $accountId);
                            $this->customerRepository->createMembership($accountId, $customerId, $membershipPlan, $billDate);
                        }
                        // For future renewal bills: don't create membership yet, wait for payment
                    }
                }
                elseif($data->billType == CustomerBillConstant::BILL_TYPE_REACTIVATION_FEE) {
                    // Handle reactivation fee - void expired membership balances
                    $this->voidExpiredMembershipBills($customerId, $accountId);
                }

                // Update the bill
                $bill = $this->customerBillRepository->update($id, $genericData);
                $bill->refresh();

                // Recalculate and update customer balance
                $customer = $this->customerRepository->findCustomerById($customerId, $accountId);
                $customer->recalculateBalance();

                // Refresh customer to load updated relationships
                $customer->refresh();
                $customer->load(['currentMembership.membershipPlan', 'currentTrainer']);

                return $bill;
            });
        } catch (\Throwable $th) {
            Log::error('Error updating customer bill', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }

    /**
     * @param int $id
     * @param int $accountId
     *
     * @return bool
     */
    public function deleteBill(int $id, int $accountId): bool
    {
        try {
            return DB::transaction(function () use ($id, $accountId) {
                $bill = $this->customerBillRepository->findBillById($id, $accountId);
                // Do not allow deleting fully paid bills
                if ($bill->bill_status === CustomerBillConstant::BILL_STATUS_PAID) {
                    throw new \RuntimeException('Cannot delete a fully paid bill. Please delete payments instead.');
                }
                // Do not allow deleting voided bills
                if ($bill->bill_status === CustomerBillConstant::BILL_STATUS_VOIDED) {
                    throw new \RuntimeException('Cannot delete a voided bill.');
                }
                $customerId = $bill->customer_id;
                $this->customerBillRepository->delete($id, $accountId);
                $customer = $this->customerRepository->findCustomerById($customerId, $accountId);
                $customer->recalculateBalance();

                // Refresh customer to load updated relationships
                $customer->refresh();
                $customer->load(['currentMembership.membershipPlan', 'currentTrainer']);

                return true;
            });
        } catch (\Throwable $th) {
            Log::error('Error deleting customer bill', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }

    /**
     * Void expired membership bills by setting net_amount to paid_amount
     * This effectively cancels any outstanding balance from expired memberships
     *
     * @param int $customerId
     * @param int $accountId
     * @return void
     */
    private function voidExpiredMembershipBills(int $customerId, int $accountId): void
    {
        // Get expired membership plan IDs from repository
        $expiredMembershipPlanIds = $this->customerRepository->getExpiredMembershipPlanIds($customerId, $accountId);

        if (empty($expiredMembershipPlanIds)) {
            return;
        }

        // Find expired membership bills with outstanding balance from repository
        $expiredMembershipBills = $this->customerBillRepository->findExpiredMembershipBillsWithOutstandingBalance(
            $customerId,
            $accountId,
            $expiredMembershipPlanIds
        );

        // Loop through bills and void each one using repository method
        $voidedCount = 0;
        foreach ($expiredMembershipBills as $bill) {
            $this->customerBillRepository->voidBill($bill->id, $accountId, $bill->paid_amount);
            $voidedCount++;
        }

        if ($voidedCount > 0) {
            Log::info('Voided expired membership bills', [
                'customer_id' => $customerId,
                'account_id' => $accountId,
                'bills_voided' => $voidedCount,
            ]);
        }
    }

    /**
     * Check if a bill is a renewal bill (for extending membership)
     * A renewal bill is one that is for a period starting after the current membership ends
     *
     * To avoid extending on old bills, we only consider it a renewal if:
     * 1. bill_date > membership_end_date (definitely a future period bill)
     * 2. OR bill_date == membership_end_date AND bill was created after the membership was created
     *    (this indicates it's a renewal bill, not an old bill from when membership was created)
     *
     * @param CustomerBill $bill
     * @param CustomerMembership|null $membership
     * @return bool
     */
    public function isRenewalBill(CustomerBill $bill, ?CustomerMembership $membership): bool
    {
        if ($bill->bill_type !== CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION || !$bill->membership_plan_id) {
            return false;
        }

        if (!$membership) {
            return false;
        }

        $billDate = Carbon::parse($bill->bill_date)->startOfDay();
        $membershipEndDate = Carbon::parse($membership->membership_end_date)->startOfDay();
        $billCreatedAt = Carbon::parse($bill->created_at);
        $membershipCreatedAt = Carbon::parse($membership->created_at);

        // Renewal bill if:
        // 1. bill_date > membership_end_date (definitely future period)
        // 2. OR bill_date == membership_end_date AND:
        //    - Bill was created after membership was created (renewal bill, not old bill), OR
        //    - Bill was created on/after the membership_end_date (automated bill created when membership is about to expire)
        //    This distinguishes renewal bills from old bills created when membership started
        $isSameDate = $billDate->equalTo($membershipEndDate);
        $billCreatedAfterMembership = $billCreatedAt->greaterThan($membershipCreatedAt);
        $billCreatedOnOrAfterEndDate = $billCreatedAt->greaterThanOrEqualTo($membershipEndDate);
        
        return $billDate->greaterThan($membershipEndDate) ||
            ($isSameDate && ($billCreatedAfterMembership || $billCreatedOnOrAfterEndDate));
    }

}
