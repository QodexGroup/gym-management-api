<?php

namespace App\Repositories\Core;

use App\Models\Core\CustomerProgress;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerProgressRepository
{
    public function getAllProgress(int $customerId): LengthAwarePaginator
    {
        return CustomerProgress::where('account_id', 1)
            ->where('customer_id', $customerId)
            ->with(['files', 'scan.files'])
            ->orderBy('recorded_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(50);
    }

    /**
     * @param array $data
     *
     * @return CustomerProgress
     */
    public function createProgress(array $data): CustomerProgress
    {
        // Set account_id to 1 by default
        $data['accountId'] = 1;
        return CustomerProgress::create($data);
    }

    /**
     * @param int $id
     * @param array $data
     *
     * @return CustomerProgress
     */
    public function updateProgress(int $id, array $data): CustomerProgress
    {
        $customerProgress = $this->getProgressById($id);

        $customerProgress->update($data);
        return $customerProgress->fresh(['files']);
    }

    /**
     * @param int $id
     *
     * @return CustomerProgress
     */
    public function getProgressById(int $id): CustomerProgress
    {
        return CustomerProgress::where('id', $id)
            ->where('account_id', 1)
            ->with(['files', 'scan.files'])
            ->firstOrFail();
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public function deleteProgress(int $id): bool
    {
        return CustomerProgress::where('id', $id)->where('account_id', 1)->delete();
    }
}
