<?php

namespace App\Services\Core;

use App\Constant\CustomerBillConstant;
use App\Constant\CustomerMembershipConstant;
use App\Helpers\GenericData;
use App\Repositories\Account\MembershipPlanRepository;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerMembershipRepository;
use App\Repositories\Core\CustomerRepository;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerMembership;
use App\Services\Account\AccountSystemSettingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerBillService
{
    public function __construct(
        private CustomerBillRepository $customerBillRepository,
        private CustomerRepository $customerRepository,
        private CustomerMembershipRepository $membershipRepository,
        private MembershipPlanRepository $membershipPlanRepository,
        private AccountSystemSettingService $membershipSettingService
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
                $this->applyBillingPeriod($genericData);
                $data = $genericData->getData();
                $customerId = $data->customerId;
                $accountId = $genericData->userData->account_id;
                $bill = null;

                if($data->billType == CustomerBillConstant::BILL_TYPE_CUSTOM_AMOUNT) {
                    $bill = $this->customerBillRepository->create($genericData);
                }
                elseif($data->billType == CustomerBillConstant::BILL_TYPE_REACTIVATION_FEE) {
                    if (!$this->membershipSettingService->get($accountId, 'requireReactivationFee')) {
                        throw new \RuntimeException('Reactivation fee is disabled for this account. Reactivate by paying a membership bill instead.');
                    }
                    // Reactivation fee amount is fixed by settings - never trust a client-supplied amount.
                    $feeAmount = (float) $this->membershipSettingService->get($accountId, 'reactivationFeeAmount');
                    $data->grossAmount = $feeAmount;
                    $data->discountPercentage = 0;
                    $data->netAmount = $feeAmount;
                    // Handle reactivation fee - void expired membership balances
                    $this->voidExpiredMembershipBills($customerId, $accountId);
                    $bill = $this->customerBillRepository->create($genericData);
                }
                else{
                    // this is for membership subscription
                    if (!$this->membershipSettingService->get($accountId, 'allowManualMembershipBills')) {
                        throw new \RuntimeException('Manual membership bills are disabled for this account. Assign or renew the membership instead.');
                    }
                    $planId = $data->billableId ?? null;

                    if ($planId) {
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
                            $membershipPlan = $this->membershipPlanRepository->findMembershipPlanById($planId, $accountId);
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
                $this->applyBillingPeriod($genericData);
                $data = $genericData->getData();
                $customerId = $data->customerId;
                $accountId = $genericData->userData->account_id;

                // Get the existing bill to check for membership plan changes
                $existingBill = $this->customerBillRepository->findBillById($id, $accountId);
                $this->ensureBillIsEditableForCurrentCycle($existingBill, $accountId);

                // Guard: a bill's net can never drop below what has already been paid on it.
                $alreadyPaid = (float) $existingBill->paid_amount;
                $newNet = isset($data->netAmount) ? (float) $data->netAmount : (float) $existingBill->net_amount;
                if ($newNet < $alreadyPaid) {
                    throw new \RuntimeException(sprintf(
                        'Net amount (%.2f) cannot be less than the amount already paid (%.2f). Delete or adjust the payments first.',
                        $newNet,
                        $alreadyPaid
                    ));
                }
                // Keep the bill status consistent with the edited amounts.
                $data->billStatus = $this->resolveBillStatus($alreadyPaid, $newNet);

                $oldMembershipPlanId = $existingBill->bill_type === CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION
                    ? $existingBill->billable_id
                    : null;
                $newMembershipPlanId = $data->billableId ?? null;

                // Handle membership subscription bill type
                if($data->billType == CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION) {
                    // Check if membership plan changed
                    if ($newMembershipPlanId && $oldMembershipPlanId != $newMembershipPlanId) {
                        // Remove old membership if it exists
                        if ($oldMembershipPlanId) {
                            $this->membershipRepository->deactivateMembershipByPlan($customerId, $oldMembershipPlanId);
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
            $this->customerBillRepository->voidBill($bill->id, $accountId);
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
        if ($bill->bill_type !== CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION || !$bill->billable_id) {
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

    /**
     * Set billing period from bill date using mdY format (e.g. 02252026).
     *
     * @param GenericData $genericData
     * @return void
     */
    private function applyBillingPeriod(GenericData $genericData): void
    {
        $billDate = $genericData->getData()->billDate ?? null;
        if (!$billDate) {
            return;
        }

        $genericData->getData()->billingPeriod = Carbon::parse($billDate)->format('mdY');
        $genericData->syncDataArray();
    }

    /**
     * Prevent edits to history/locked bills from previous membership cycles.
     *
     * @param CustomerBill $bill
     * @param int $accountId
     * @return void
     */
    /**
     * Derive a bill's status from its paid vs net amounts.
     *
     * @param float $paid
     * @param float $net
     * @return string
     */
    private function resolveBillStatus(float $paid, float $net): string
    {
        if ($paid <= 0) {
            return CustomerBillConstant::BILL_STATUS_ACTIVE;
        }

        if ($paid >= $net) {
            return CustomerBillConstant::BILL_STATUS_PAID;
        }

        return CustomerBillConstant::BILL_STATUS_PARTIAL;
    }

    private function ensureBillIsEditableForCurrentCycle(CustomerBill $bill, int $accountId): void
    {
        if ($bill->bill_status === CustomerBillConstant::BILL_STATUS_VOIDED) {
            throw new \RuntimeException('Cannot update a voided bill.');
        }

        if ($bill->bill_type !== CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION) {
            return;
        }

        // Editing previous-cycle bills is locked unless the account allows it.
        if ($this->membershipSettingService->get($accountId, 'allowEditPreviousCycleBills')) {
            return;
        }

        $customer = $this->customerRepository->findCustomerById((int) $bill->customer_id, $accountId);
        $currentMembership = $customer->currentMembership;
        if (!$currentMembership) {
            return;
        }

        $billDate = Carbon::parse($bill->bill_date)->startOfDay();
        $membershipStartDate = Carbon::parse($currentMembership->membership_start_date)->startOfDay();
        if ($billDate->lt($membershipStartDate)) {
            throw new \RuntimeException('Cannot update a bill from a previous billing period.');
        }
    }

}
