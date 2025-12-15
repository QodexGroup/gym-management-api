<?php

namespace App\Repositories\Core;

use App\Models\Core\CustomerPayment;
use Illuminate\Database\Eloquent\Collection;

class CustomerPaymentRepository
{
    /**
     * Create a new customer payment.
     *
     * @param array $data
     * @return CustomerPayment
     */
    public function create(array $data): CustomerPayment
    {
        // Set defaults
        $data['accountId'] = $data['accountId'] ?? 1;
        $data['createdBy'] = $data['createdBy'] ?? 1;
        $data['updatedBy'] = $data['updatedBy'] ?? 1;

        return CustomerPayment::create($data);
    }

    /**
     * Get a payment by id.
     *
     * @param int $id
     * @return CustomerPayment
     */
    public function getById(int $id): CustomerPayment
    {
        return CustomerPayment::where('account_id', 1)
            ->with([ 'bill'])
            ->findOrFail($id);
    }

    /**
     * Get all payments for a bill.
     *
     * @param int $billId
     * @return Collection<int, CustomerPayment>
     */
    public function getByBillId(int $billId): Collection
    {
        return CustomerPayment::where('account_id', 1)
            ->where('customer_bill_id', $billId)
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    /**
     * Delete a payment (soft delete).
     *
     * @param int $id
     * @return CustomerPayment
     */
    public function delete(int $id, int $customerBillId): CustomerPayment
    {
        $payment = CustomerPayment::where('account_id', 1)
        ->where('customer_bill_id', $customerBillId)->findOrFail($id);
        $payment->delete();

        return $payment;
    }
}

