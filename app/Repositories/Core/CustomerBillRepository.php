<?php

namespace App\Repositories\Core;

use App\Helpers\GenericData;
use App\Models\Core\CustomerBill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CustomerBillRepository
{
    /**
     * Create a new bill
     *
     * @param GenericData $genericData
     * @return CustomerBill
     */
    public function create(GenericData $genericData): CustomerBill
    {
        // Ensure account_id is set in data
        $genericData->getData()->accountId = $genericData->userData->account_id;
        $genericData->getData()->paidAmount = $genericData->getData()->paidAmount ?? 0;
        $genericData->getData()->createdBy = $genericData->getData()->createdBy ?? $genericData->userData->id;
        $genericData->getData()->updatedBy = $genericData->getData()->updatedBy ?? $genericData->userData->id;
        $genericData->syncDataArray();

        return CustomerBill::create($genericData->data)->fresh();
    }

    /**
     * Find a bill by ID and account ID
     *
     * @param int $id
     * @param int $accountId
     * @return CustomerBill
     */
    public function findBillById(int $id, int $accountId): CustomerBill
    {
        return CustomerBill::where('id', $id)
            ->where('account_id', $accountId)
            ->with(['creator', 'updater', 'membershipPlan'])
            ->firstOrFail();
    }

    /**
     * Update a bill
     *
     * @param int $id
     * @param GenericData $genericData
     * @return CustomerBill
     */
    public function update(int $id, GenericData $genericData): CustomerBill
    {
        $bill = $this->findBillById($id, $genericData->userData->account_id);
        // Set updatedBy if not provided
        $genericData->getData()->updatedBy = $genericData->getData()->updatedBy ?? $genericData->userData->id;
        $genericData->syncDataArray();
        $bill->update($genericData->data);
        return $bill->fresh(['creator', 'updater', 'membershipPlan']);
    }

    /**
     * Update bill paid amount and status (used by payments)
     *
     * @param int $id
     * @param int $accountId
     * @param float $paidAmount
     * @param string $status
     * @param int $updatedBy
     * @return CustomerBill
     */
    public function updatePaidAmount(int $id, int $accountId, float $paidAmount, string $status, int $updatedBy): CustomerBill
    {
        $bill = $this->findBillById($id, $accountId);
        $bill->paid_amount = $paidAmount;
        $bill->bill_status = $status;
        $bill->updated_by = $updatedBy;
        $bill->save();

        return $bill->fresh(['creator', 'updater']);
    }

    /**
     * Delete a bill
     *
     * @param int $id
     * @param int $accountId
     * @return bool
     */
    public function delete(int $id, int $accountId): bool
    {
        $bill = $this->findBillById($id, $accountId);
        return $bill->delete();
    }

    /**
     * Get bills by customer ID with pagination, filtering, and sorting
     *
     * @param int $customerId
     * @param GenericData $genericData
     * @return LengthAwarePaginator
     */
    public function getByCustomerId(GenericData $genericData): LengthAwarePaginator
    {
        $query = CustomerBill::where('customer_id', $genericData->customerId)
            ->where('account_id', $genericData->userData->account_id);

        // Apply relations, filters, and sorts using GenericData methods
        $query = $genericData->applyRelations($query, ['creator', 'updater', 'membershipPlan']);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        // Always return paginated results
        return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
    }
}

