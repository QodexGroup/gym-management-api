<?php

namespace App\Repositories\Core;

use App\Models\Core\CustomerBill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerBillRepository
{

    /**
     * Create a new bill
     *
     * @param array $data
     * @return CustomerBill
     */
    public function create(array $data): CustomerBill
    {
        // Set defaults
        $data['accountId'] = 1;
        $data['paidAmount'] = $data['paidAmount'] ?? 0;
        $data['createdBy'] = $data['createdBy'] ?? 1;
        $data['updatedBy'] = $data['updatedBy'] ?? 1;

        return CustomerBill::create($data);
    }

    /**
     * Get a bill by id
     *
     * @param int $id
     * @return CustomerBill
     */
    public function getById(int $id): CustomerBill
    {
        return CustomerBill::where('account_id', 1)
            ->with(['creator', 'updater', 'membershipPlan'])
            ->findOrFail($id);
    }

    /**
     * Update a bill
     *
     * @param int $id
     * @param array $data
     * @return CustomerBill
     */
    public function update(int $id, array $data): CustomerBill
    {
        $bill = CustomerBill::where('account_id', 1)->findOrFail($id);
        // Set updatedBy if not provided
        $data['updatedBy'] = $data['updatedBy'] ?? 1;
        $bill->update($data);
        return $bill->fresh(['creator', 'updater']);
    }

    /**
     * Update bill paid amount and status (used by payments)
     *
     * @param int $id
     * @param float $paidAmount
     * @param string $status
     * @return CustomerBill
     */
    public function updatePaidAmount(int $id, float $paidAmount, string $status): CustomerBill
    {
        $bill = CustomerBill::where('account_id', 1)->findOrFail($id);
        $bill->paid_amount = $paidAmount;
        $bill->bill_status = $status;
        $bill->updated_by = 1;
        $bill->save();

        return $bill->fresh(['creator', 'updater']);
    }

    /**
     * Delete a bill
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $bill = CustomerBill::where('account_id', 1)->findOrFail($id);
        return $bill->delete();
    }

    /**
     * Get bills by customer ID
     *
     * @param int $customerId
     * @return LengthAwarePaginator
     */
    public function getByCustomerId(int $customerId): LengthAwarePaginator
    {
        return CustomerBill::where('customer_id', $customerId)
            ->with(['creator', 'updater', 'membershipPlan'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);
    }
}

