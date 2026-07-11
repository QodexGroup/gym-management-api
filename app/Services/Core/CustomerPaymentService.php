<?php

namespace App\Services\Core;

use App\Constant\CustomerBillConstant;
use App\Constant\MembershipSettingConstant;
use App\Helpers\GenericData;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerPayment;
use App\Repositories\Account\MembershipPlanRepository;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerPaymentRepository;
use App\Repositories\Core\CustomerRepository;
use App\Services\Account\AccountSystemSettingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerPaymentService
{
    public function __construct(
        private CustomerPaymentRepository $paymentRepository,
        private CustomerBillRepository $billRepository,
        private CustomerRepository $customerRepository,
        private MembershipPlanRepository $membershipPlanRepository,
        private CustomerBillService $billService,
        private NotificationService $notificationService,
        private AccountSystemSettingService $membershipSettingService,
    ) {
    }

    /**
     * Add a payment for a customer bill.
     *
     * @param GenericData $genericData
     * @return CustomerPayment
     */
    public function addPayment(GenericData $genericData): CustomerPayment
    {
        try {
            return DB::transaction(function () use ($genericData) {
                $data = $genericData->getData();
                $accountId = $genericData->userData->account_id;
                $updatedBy = $genericData->userData->id;

                $customerId = (int) $data->customerId;
                $billId = (int) $data->customerBillId;
                $amount = (float) $data->amount;

                /** @var CustomerBill $bill */
                $bill = $this->billRepository->findBillById($billId, $accountId);

                $this->validatePaymentRequest($bill, $customerId, $amount, $accountId);

                if (!isset($genericData->getData()->paymentMethod)) {
                    $genericData->getData()->paymentMethod = 'cash';
                    $genericData->syncDataArray();
                }

                /** @var CustomerPayment $payment */
                $payment = $this->paymentRepository->create($genericData);

                $newPaidAmount = (float) $bill->paid_amount + $amount;
                $newStatus = $this->determineBillStatus($bill->net_amount, $newPaidAmount);
                $this->billRepository->updatePaidAmount($billId, $accountId, $newPaidAmount, $newStatus, $updatedBy);

                $this->customerRepository->findCustomerById($customerId, $accountId)->recalculateBalance();

                // Handle reactivation fee payment - create membership with free month
                if ($bill->bill_type === CustomerBillConstant::BILL_TYPE_REACTIVATION_FEE) {
                    $this->handleReactivationFeePayment($bill, $newPaidAmount, $accountId, $payment);
                } else {
                    // Check if this is an automated membership subscription bill that needs membership extension
                    $this->handleAutomatedBillPayment($bill, $newPaidAmount, $accountId);
                }

                // Send payment notification
                $this->notificationService->createPaymentReceivedNotification($payment);

                return $payment->fresh(['customer', 'bill', 'creator', 'updater']);
            });
        } catch (\Throwable $th) {
            Log::error('Error adding customer payment', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }

    /**
     * Delete a payment and update bill & customer balance.
     *
     * @param int $id
     * @param int $accountId
     * @param int $updatedBy
     * @return bool
     */
    public function deletePayment(int $id, int $accountId, int $updatedBy): bool
    {
        try {
            DB::transaction(function () use ($id, $accountId, $updatedBy) {
                /** @var CustomerPayment $payment */
                $payment = $this->paymentRepository->getById($id, $accountId);

                $billId = (int) $payment->customer_bill_id;
                $customerId = (int) $payment->customer_id;

                /** @var CustomerBill $bill */
                $bill = $this->billRepository->findBillById($billId, $accountId);
                $customer = $this->customerRepository->findCustomerById($customerId, $accountId);

                $amount = (float) $payment->amount;

                // Soft delete the payment
                $this->paymentRepository->delete($id, $billId, $accountId);

                // Recalculate bill paid amount and status via dedicated repository method
                $currentPaid = (float) $bill->paid_amount;
                $newPaidAmount = max(0, $currentPaid - $amount);
                $newStatus = $this->determineBillStatus($bill->net_amount, $newPaidAmount);

                $this->billRepository->updatePaidAmount($billId, $accountId, $newPaidAmount, $newStatus, $updatedBy);

                // Recalculate customer balance
                $customer->recalculateBalance();
            });

            return true;
        } catch (\Throwable $th) {
            Log::error('Error deleting customer payment', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            throw $th;
        }
    }

    /**
     * Get all payments for a given bill.
     *
     * @param int $billId
     * @param int $accountId
     * @return \Illuminate\Database\Eloquent\Collection<int, CustomerPayment>
     */
    public function getPaymentsForBill(int $billId, int $accountId)
    {
        return $this->paymentRepository->getByBillId($billId, $accountId);
    }

    /**
     * Validate that a payment can be applied to a bill.
     */
    private function validatePaymentRequest(CustomerBill $bill, int $customerId, float $amount, int $accountId): void
    {
        if ($bill->customer_id !== $customerId) {
            throw new \RuntimeException('Bill does not belong to the specified customer.');
        }

        $this->ensureBillIsPayable($bill, (int) $accountId);

        $remaining = (float) $bill->net_amount - (float) $bill->paid_amount;
        if ($amount <= 0 || $amount > $remaining) {
            throw new \RuntimeException('Invalid payment amount.');
        }

        // When partial payments are disabled, a payment must settle the full remaining balance.
        if (!$this->membershipSettingService->get($accountId, 'allowPartialPayments')
            && ($remaining - $amount) > 0.001) {
            throw new \RuntimeException(sprintf(
                'Partial payments are disabled for this account. Please pay the full remaining balance of %.2f.',
                $remaining
            ));
        }
    }

    /**
     * Determine bill status based on net and paid amounts.
     *
     * @param float|int $netAmount
     * @param float|int $paidAmount
     * @return string
     */
    private function determineBillStatus(float|int $netAmount, float|int $paidAmount): string
    {
        if ($paidAmount >= $netAmount && $netAmount > 0) {
            return CustomerBillConstant::BILL_STATUS_PAID;
        }

        if ($paidAmount > 0 && $paidAmount < $netAmount) {
            return CustomerBillConstant::BILL_STATUS_PARTIAL;
        }

        return CustomerBillConstant::BILL_STATUS_ACTIVE;
    }

    /**
     * Handle bill payment - extend/create membership if payment is made on membership subscription bill
     * Works for both automated and manual renewal bills
     *
     * @param CustomerBill $bill
     * @param float $newPaidAmount
     * @param int $accountId
     * @return void
     */
    private function handleAutomatedBillPayment(CustomerBill $bill, float $newPaidAmount, int $accountId): void
    {
        // Only process membership subscription bills
        if ($bill->bill_type !== CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION || !$bill->billable_id) {
            return;
        }

        // Only extend if payment was made (even partial)
        if ($newPaidAmount <= 0) {
            return;
        }

        // Grant/extend policy: 'first_payment' activates on any (partial) payment,
        // 'full_payment' waits until the bill is fully paid.
        $grantOn = $this->membershipSettingService->getForAccount($accountId)['grantMembershipOn'];
        if ($grantOn === MembershipSettingConstant::GRANT_ON_FULL_PAYMENT
            && $newPaidAmount < (float) $bill->net_amount) {
            return;
        }

        try {
            // Find the current active membership for this customer and plan
            $membership = $this->customerRepository->findLatestMembershipForPlan(
                $bill->customer_id,
                $accountId,
                (int) $bill->billable_id
            );

            // A scheduled (next-renewal) plan change: the bill is for the customer's
            // pending plan while the active membership is still on the old plan. Switch
            // the membership onto the new plan and extend it for the new period.
            if (!$membership) {
                $pendingMembership = $this->customerRepository->findMembershipWithPendingPlan(
                    $bill->customer_id,
                    $accountId,
                    (int) $bill->billable_id
                );

                if ($pendingMembership) {
                    $membershipPlan = $this->membershipPlanRepository->findMembershipPlanById((int) $bill->billable_id, $accountId);
                    $billDate = Carbon::parse($bill->bill_date)->startOfDay();
                    $membershipEndDate = Carbon::parse($pendingMembership->membership_end_date)->startOfDay();
                    $newStartDate = $billDate->greaterThan($membershipEndDate) ? $billDate : $membershipEndDate;
                    // A prorated bridge bill carries an explicit coverage end; otherwise a full plan period.
                    $newEndDate = $bill->coverage_end_date
                        ? Carbon::parse($bill->coverage_end_date)->startOfDay()
                        : $membershipPlan->calculateEndDate($newStartDate);

                    // Switch to the new plan (clears the pending flag), then extend dates.
                    $this->customerRepository->applyPendingPlan($pendingMembership->id, (int) $bill->billable_id);
                    $this->customerRepository->extendMembership($pendingMembership->id, $newStartDate, $newEndDate);

                    Log::info('Scheduled plan change applied on renewal payment', [
                        'membership_id' => $pendingMembership->id,
                        'bill_id' => $bill->id,
                        'customer_id' => $bill->customer_id,
                        'new_plan_id' => $bill->billable_id,
                        'new_start_date' => $newStartDate->toDateString(),
                        'new_end_date' => $newEndDate->toDateString(),
                    ]);
                    return;
                }
            }

            if (!$membership) {
                // No existing membership - this is a new member, create membership
                $membershipPlan = $this->membershipPlanRepository->findMembershipPlanById((int) $bill->billable_id, $accountId);
                $billDate = Carbon::parse($bill->bill_date);

                // A prorated bridge bill carries an explicit coverage end; honour it so the
                // membership spans only the bridge period (not a full plan period).
                if ($bill->coverage_end_date) {
                    $this->customerRepository->createMembershipWithDates(
                        $bill->customer_id,
                        $membershipPlan,
                        $billDate->copy()->startOfDay(),
                        Carbon::parse($bill->coverage_end_date)->startOfDay()
                    );
                } else {
                    $this->customerRepository->createMembership($accountId, $bill->customer_id, $membershipPlan, $billDate);
                }

                Log::info('Membership created for new member via bill payment', [
                    'bill_id' => $bill->id,
                    'customer_id' => $bill->customer_id,
                    'billable_id' => $bill->billable_id,
                    'start_date' => $billDate->toDateString(),
                ]);
                return;
            }

            // Check if this is a renewal bill (for extending membership)
            $isRenewalBill = $this->billService->isRenewalBill($bill, $membership);

            if ($isRenewalBill) {
                $billDate = Carbon::parse($bill->bill_date)->startOfDay();
                $membershipEndDate = Carbon::parse($membership->membership_end_date)->startOfDay();
                $membershipPlan = $this->membershipPlanRepository->findMembershipPlanById((int) $bill->billable_id, $accountId);

                // New start date = bill date (or membership end date, whichever is later)
                $newStartDate = $billDate->greaterThan($membershipEndDate) ? $billDate : $membershipEndDate;
                // A prorated bridge bill carries an explicit coverage end; otherwise a full plan period.
                $newEndDate = $bill->coverage_end_date
                    ? Carbon::parse($bill->coverage_end_date)->startOfDay()
                    : $membershipPlan->calculateEndDate($newStartDate);

                // Extend the membership
                $this->customerRepository->extendMembership($membership->id, $newStartDate, $newEndDate);

                Log::info('Membership extended due to bill payment', [
                    'membership_id' => $membership->id,
                    'bill_id' => $bill->id,
                    'customer_id' => $bill->customer_id,
                    'new_start_date' => $newStartDate->toDateString(),
                    'new_end_date' => $newEndDate->toDateString(),
                    'paid_amount' => $newPaidAmount,
                ]);
            }
        } catch (\Throwable $th) {
            // Log error but don't fail the payment
            Log::error('Error handling bill payment for membership extension', [
                'bill_id' => $bill->id,
                'customer_id' => $bill->customer_id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle reactivation fee payment - create new membership with free month
     *
     * @param CustomerBill $bill
     * @param float $newPaidAmount
     * @param int $accountId
     * @param CustomerPayment $payment
     * @return void
     */
    private function handleReactivationFeePayment(CustomerBill $bill, float $newPaidAmount, int $accountId, CustomerPayment $payment): void
    {
        // Only process if payment was made (even partial)
        if ($newPaidAmount <= 0) {
            return;
        }

        try {
            // Find the last expired membership
            $lastExpiredMembership = $this->customerRepository->getLastExpiredMembership($bill->customer_id, $accountId);

            if (!$lastExpiredMembership) {
                Log::warning('No expired membership found for reactivation fee payment', [
                    'bill_id' => $bill->id,
                    'customer_id' => $bill->customer_id,
                ]);
                return;
            }

            // Get the membership plan from the last expired membership
            $membershipPlan = $this->membershipPlanRepository->findMembershipPlanById(
                $lastExpiredMembership->membership_plan_id,
                $accountId
            );

            // Payment date = start date for new membership (promo period starts from payment date)
            // Use payment_date if set, otherwise use payment's created_at, fallback to now()
            $paymentDate = $payment->payment_date
                ? Carbon::parse($payment->payment_date)
                : ($payment->created_at ? Carbon::parse($payment->created_at) : Carbon::now());

            // Reactivation grant is account-configurable: a promo period (default 1 month)
            // when grantReactivationPromo is on, otherwise the plan's normal period.
            $settings = $this->membershipSettingService->getForAccount($accountId);
            $start = $paymentDate->copy()->startOfDay();
            if ($settings['grantReactivationPromo']) {
                $length = (int) $settings['reactivationPromoLength'];
                $endDate = $settings['reactivationPromoUnit'] === MembershipSettingConstant::PROMO_UNIT_DAYS
                    ? $start->copy()->addDays($length)->subDay()->startOfDay()
                    : $start->copy()->addMonths($length)->subDay()->startOfDay();
            } else {
                $endDate = $membershipPlan->calculateEndDate($start);
            }

            $newMembership = $this->customerRepository->createMembershipWithDates(
                $bill->customer_id,
                $membershipPlan,
                $paymentDate,
                $endDate
            );

            Log::info('Membership created via reactivation fee payment', [
                'bill_id' => $bill->id,
                'customer_id' => $bill->customer_id,
                'membership_id' => $newMembership->id,
                'membership_plan_id' => $membershipPlan->id,
                'start_date' => $newMembership->membership_start_date->toDateString(),
                'end_date' => $newMembership->membership_end_date->toDateString(),
                'paid_amount' => $newPaidAmount,
                'last_expired_membership_id' => $lastExpiredMembership->id,
            ]);
        } catch (\Throwable $th) {
            // Log error but don't fail the payment
            Log::error('Error handling reactivation fee payment for membership creation', [
                'bill_id' => $bill->id,
                'customer_id' => $bill->customer_id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
        }
    }

    /**
     * Prevent payments on voided bills. Previous-cycle membership bills are payable
     * by default, but the account setting `allowPayPreviousCycleBills` can lock them.
     *
     * @param CustomerBill $bill
     * @param int $accountId
     * @return void
     */
    private function ensureBillIsPayable(CustomerBill $bill, int $accountId): void
    {
        if ($bill->bill_status === CustomerBillConstant::BILL_STATUS_VOIDED) {
            throw new \RuntimeException('Cannot add payment to a voided bill.');
        }

        if ($bill->bill_type !== CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION) {
            return;
        }

        // Previous-cycle membership bills stay payable unless the account disables it.
        if ($this->membershipSettingService->get($accountId, 'allowPayPreviousCycleBills')) {
            return;
        }

        $customer = $this->customerRepository->findCustomerById((int) $bill->customer_id, $accountId);
        $currentMembership = $customer?->currentMembership;
        if (!$currentMembership) {
            return;
        }

        $billDate = Carbon::parse($bill->bill_date)->startOfDay();
        $membershipStartDate = Carbon::parse($currentMembership->membership_start_date)->startOfDay();
        if ($billDate->lt($membershipStartDate)) {
            throw new \RuntimeException('Cannot add payment to a bill from a previous billing period.');
        }
    }
}
