<?php

namespace App\Repositories\Core;

use App\Helpers\GenericData;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Core\CustomerScans;
use Illuminate\Database\Eloquent\Collection;

class CustomerScanRepository
{
    /**
     * @param GenericData $genericData
     * @return LengthAwarePaginator
     */
    public function getAllScans(GenericData $genericData): LengthAwarePaginator
    {
        $accountId = $genericData->userData->account_id;
        $query = CustomerScans::where('customer_id', $genericData->customerId)
            ->where('account_id', $accountId);

        // Set default relations if not specified
        if (empty($genericData->relations)) {
            $genericData->relations = [
                'files' => function ($query) use ($accountId) {
                    $query->where('account_id', $accountId);
                }
            ];
        }

        $genericData->applyRelations($query);
        $genericData->applyFilters($query);
        $genericData->applySorts($query);

        return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
    }

    /**
     * @param GenericData $genericData
     *
     * @return CustomerScans
     */
    public function createScan(GenericData $genericData): CustomerScans
    {
        $data = $genericData->data;
        $data['account_id'] = $genericData->userData->account_id;
        $data['uploaded_by'] = $genericData->userData->id;
        return CustomerScans::create($data);
    }

    /**
     * @param int $id
     * @param GenericData $genericData
     *
     * @return CustomerScans
     */
    public function updateScan(int $id, GenericData $genericData): CustomerScans
    {
        $customerScan = $this->getScanById($id, $genericData->userData->account_id);
        $customerScan->uploaded_by = $genericData->userData->id;
        $customerScan->update($genericData->data);
        return $customerScan->fresh(['files']);
    }

    /**
     * @param int $id
     * @param int $accountId
     *
     * @return CustomerScans
     */
    public function getScanById(int $id, int $accountId): CustomerScans
    {
        return CustomerScans::where('id', $id)
            ->where('account_id', $accountId)
            ->with(['files'])
            ->firstOrFail();
    }

    /**
     * @param int $id
     * @param int $accountId
     *
     * @return bool
     */
    public function deleteScan(int $id, int $accountId): bool
    {
        return CustomerScans::where('id', $id)->where('account_id', $accountId)->delete();
    }

    /**
     * Get scans by customer ID and scan type
     *
     * @param GenericData $genericData
     * @param string $scanType
     * @return Collection
     */
    public function getScansByType(GenericData $genericData, string $scanType): Collection
    {
        $query = CustomerScans::where('customer_id', $genericData->customerId)
            ->where('account_id', $genericData->userData->account_id)
            ->where('scan_type', $scanType);

        $query->with(['files']);
        $query->orderBy('scan_date', 'desc');
        $query->orderBy('id', 'desc');

        return $query->get();
    }
}
