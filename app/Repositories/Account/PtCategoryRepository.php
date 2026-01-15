<?php

namespace App\Repositories\Account;

use App\Helpers\GenericData;
use App\Models\Account\PtCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PtCategoryRepository
{
    /**
     * Get all PT categories
     *
     * @param GenericData $genericData
     * @return LengthAwarePaginator|Collection
     */
    public function getAllPtCategories(GenericData $genericData): LengthAwarePaginator|Collection
    {
        $query = PtCategory::where('account_id', $genericData->userData->account_id);

        // Apply relations, filters, and sorts using GenericData methods
        $query = $genericData->applyRelations($query);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        // Check if pagination is requested
        if ($genericData->pageSize > 0) {
            return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
        }

        return $query->get();
    }

    /**
     * Get a PT category by ID
     *
     * @param int $id
     * @param int $accountId
     * @return PtCategory
     */
    public function findPtCategoryById(int $id, int $accountId): PtCategory
    {
        return PtCategory::where('id', $id)
            ->where('account_id', $accountId)
            ->firstOrFail();
    }
}
