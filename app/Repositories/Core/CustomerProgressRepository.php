<?php

namespace App\Repositories\Core;

use App\Helpers\GenericData;
use App\Models\Core\CustomerProgress;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerProgressRepository
{
    public function getAllProgress(GenericData $genericData): LengthAwarePaginator
    {
        $accountId = $genericData->userData->account_id;
        $query = CustomerProgress::where('account_id', $accountId)
            ->where('customer_id', $genericData->customerId);

        // Set default relations if not specified
        if (empty($genericData->relations)) {
            $genericData->relations = [
                'files' => function ($query) use ($accountId) {
                    $query->where('account_id', $accountId);
                },
                'scan.files' => function ($query) use ($accountId) {
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
     * @return CustomerProgress
     */
    public function createProgress(GenericData $genericData): CustomerProgress
    {
        $data = $genericData->data;
        $data['account_id'] = $genericData->userData->account_id;
        $data['recorded_by'] = $genericData->userData->id;
        return CustomerProgress::create($data);
    }

    /**
     * @param int $id
     * @param GenericData $genericData
     *
     * @return CustomerProgress
     */
    public function updateProgress(int $id, GenericData $genericData): CustomerProgress
    {
        $customerProgress = $this->getProgressById($id, $genericData->userData->account_id);
        $customerProgress->recorded_by = $genericData->userData->id;
        $customerProgress->update($genericData->data);
        return $customerProgress->fresh(['files']);
    }

    /**
     * @param int $id
     * @param int $accountId
     *
     * @return CustomerProgress
     */
    public function getProgressById(int $id, int $accountId): CustomerProgress
    {
        return CustomerProgress::where('id', $id)
            ->where('account_id', $accountId)
            ->with(['files', 'scan.files'])
            ->firstOrFail();
    }

    /**
     * @param int $id
     * @param int $accountId
     *
     * @return bool
     */
    public function deleteProgress(int $id, int $accountId): bool
    {
        return CustomerProgress::where('id', $id)->where('account_id', $accountId)->delete();
    }
}
