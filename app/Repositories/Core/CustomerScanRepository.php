<?php

namespace App\Repositories\Core;

use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Core\CustomerScans;

class CustomerScanRepository
{
    /**
     * @return LengthAwarePaginator
     */
    public function getAllScans(int $customerId): LengthAwarePaginator
    {
        return CustomerScans::where('customer_id', $customerId)
            ->where('account_id', 1)
            ->with(['files'])
            ->orderBy('scan_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(50);
    }

    /**
     * @param array $data
     *
     * @return CustomerScan
     */
    public function createScan(array $data): CustomerScans
    {
        $data['account_id'] = 1;
        return CustomerScans::create($data);
    }

    /**
     * @param int $id
     * @param array $data
     *
     * @return CustomerScans
     */
    public function updateScan(int $id, array $data): CustomerScans
    {
        $customerScan = $this->getScanById($id);
        $customerScan->update($data);
        return $customerScan->fresh(['files']);
    }

    /**
     * @param int $id
     *
     * @return CustomerScans
     */
    public function getScanById(int $id): CustomerScans
    {
        return CustomerScans::where('id', $id)
            ->where('account_id', 1)
            ->with(['files'])
            ->firstOrFail();
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public function deleteScan(int $id): bool
    {
        return CustomerScans::where('id', $id)->where('account_id', 1)->delete();
    }

    /**
     * Get scans by customer ID and scan type
     *
     * @param int $customerId
     * @param string $scanType
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getScansByType(int $customerId, string $scanType)
    {
        $query = CustomerScans::where('customer_id', $customerId)
            ->where('account_id', 1)
            ->where('scan_type', $scanType);

        // Using QueryHelper for consistency, but getting all results
        $query->with(['files']);
        $query->orderBy('scan_date', 'desc');
        $query->orderBy('id', 'desc');

        return $query->get();
    }
}
