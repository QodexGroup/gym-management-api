<?php

namespace App\Repositories\Core;

use App\Helpers\GenericData;
use App\Models\Core\CustomerPayment;
use Illuminate\Database\Eloquent\Collection;

class CustomerPaymentRepository
{
    /**
     * Create a new customer payment.
     *
     * @param GenericData $genericData
     * @return CustomerPayment
     */
    public function create(GenericData $genericData): CustomerPayment
    {
        // Ensure account_id, createdBy, and updatedBy are set in data
        $genericData->getData()->accountId = $genericData->userData->account_id;
        $genericData->getData()->createdBy = $genericData->getData()->createdBy ?? $genericData->userData->id;
        $genericData->getData()->updatedBy = $genericData->getData()->updatedBy ?? $genericData->userData->id;
        $genericData->syncDataArray();

        return CustomerPayment::create($genericData->data);
    }

    /**
     * Get a payment by id and account id.
     *
     * @param int $id
     * @param int $accountId
     * @return CustomerPayment
     */
    public function getById(int $id, int $accountId): CustomerPayment
    {
        return CustomerPayment::where('id', $id)
            ->where('account_id', $accountId)
            ->with(['bill'])
            ->firstOrFail();
    }

    /**
     * Get all payments for a bill.
     *
     * @param int $billId
     * @param int $accountId
     * @return Collection<int, CustomerPayment>
     */
    public function getByBillId(int $billId, int $accountId): Collection
    {
        return CustomerPayment::where('account_id', $accountId)
            ->where('customer_bill_id', $billId)
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    /**
     * Delete a payment (soft delete).
     *
     * @param int $id
     * @param int $customerBillId
     * @param int $accountId
     * @return CustomerPayment
     */
    public function delete(int $id, int $customerBillId, int $accountId): CustomerPayment
    {
        $payment = CustomerPayment::where('id', $id)
            ->where('account_id', $accountId)
            ->where('customer_bill_id', $customerBillId)
            ->firstOrFail();
        $payment->delete();

        return $payment;
    }
}

