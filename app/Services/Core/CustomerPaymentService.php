<?php

namespace App\Services\Core;

use App\Constant\CustomerBillConstant;
use App\Helpers\GenericData;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerPayment;
use App\Repositories\Account\MembershipPlanRepository;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerPaymentRepository;
use App\Repositories\Core\CustomerRepository;
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
        private NotificationService $notificationService,
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

                if ($bill->customer_id !== $customerId) {
                    throw new \RuntimeException('Bill does not belong to the specified customer.');
                }

                $netAmount = (float) $bill->net_amount;
                $paidAmount = (float) $bill->paid_amount;
                $remaining = $netAmount - $paidAmount;

                if ($amount <= 0 || $amount > $remaining) {
                    throw new \RuntimeException('Invalid payment amount.');
                }

                // Ensure paymentMethod has a default value
                if (!isset($genericData->getData()->paymentMethod)) {
                    $genericData->getData()->paymentMethod = 'cash';
                    $genericData->syncDataArray();
                }

                /** @var CustomerPayment $payment */
                $payment = $this->paymentRepository->create($genericData);

                // Update bill paid amount and status via dedicated repository method
                $newPaidAmount = $paidAmount + $amount;
                $newStatus = $this->determineBillStatus($bill->net_amount, $newPaidAmount);

                $this->billRepository->updatePaidAmount($billId, $accountId, $newPaidAmount, $newStatus, $updatedBy);

                // Recalculate customer balance using existing model method
                $customer = $this->customerRepository->findCustomerById($customerId, $accountId);
                $customer->recalculateBalance();

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
        if ($bill->bill_type !== CustomerBillConstant::BILL_TYPE_MEMBERSHIP_SUBSCRIPTION || !$bill->membership_plan_id) {
            return;
        }

        // Only extend if payment was made (even partial)
        if ($newPaidAmount <= 0) {
            return;
        }

        try {
            // Find the current active membership for this customer and plan
            $membership = $this->customerRepository->findLatestMembershipForPlan(
                $bill->customer_id,
                $accountId,
                $bill->membership_plan_id
            );

            if (!$membership) {
                // No existing membership - this is a new member, create membership
                $membershipPlan = $this->membershipPlanRepository->findMembershipPlanById($bill->membership_plan_id, $accountId);
                $billDate = Carbon::parse($bill->bill_date);

                $this->customerRepository->createMembership($accountId, $bill->customer_id, $membershipPlan, $billDate);

                Log::info('Membership created for new member via bill payment', [
                    'bill_id' => $bill->id,
                    'customer_id' => $bill->customer_id,
                    'membership_plan_id' => $bill->membership_plan_id,
                    'start_date' => $billDate->toDateString(),
                ]);
                return;
            }

            // Check if bill_date matches or is after the membership end date (renewal bill)
            $billDate = Carbon::parse($bill->bill_date)->startOfDay();
            $membershipEndDate = Carbon::parse($membership->membership_end_date)->startOfDay();

            // Renewal bill: bill_date >= membership_end_date
            if ($billDate->greaterThanOrEqualTo($membershipEndDate)) {
                $membershipPlan = $this->membershipPlanRepository->findMembershipPlanById($bill->membership_plan_id, $accountId);

                // New start date = bill date (or membership end date, whichever is later)
                $newStartDate = $billDate->greaterThan($membershipEndDate) ? $billDate : $membershipEndDate;
                // New end date = start date + plan period
                $newEndDate = $membershipPlan->calculateEndDate($newStartDate);

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

            // Payment date = start date for new membership (free month starts from payment date)
            // Use payment_date if set, otherwise use payment's created_at, fallback to now()
            $paymentDate = $payment->payment_date
                ? Carbon::parse($payment->payment_date)
                : ($payment->created_at ? Carbon::parse($payment->created_at) : Carbon::now());

            // Create new membership with free month (1 month free, regardless of plan type)
            $newMembership = $this->customerRepository->createMembershipWithFreeMonth(
                $accountId,
                $bill->customer_id,
                $membershipPlan,
                $paymentDate
            );

            Log::info('Membership created with free month via reactivation fee payment', [
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
}

