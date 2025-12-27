<?php

namespace App\Services\Core;

use App\Constant\CustomerBillConstant;
use App\Helpers\GenericData;
use App\Models\Core\CustomerBill;
use App\Models\Core\CustomerPayment;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerPaymentRepository;
use App\Repositories\Core\CustomerRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerPaymentService
{
    public function __construct(
        private CustomerPaymentRepository $paymentRepository,
        private CustomerBillRepository $billRepository,
        private CustomerRepository $customerRepository,
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
}

