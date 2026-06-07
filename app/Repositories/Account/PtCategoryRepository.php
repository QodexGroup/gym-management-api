<?php

namespace App\Repositories\Account;

use App\Repositories\BaseRepository;

use App\Helpers\GenericData;
use App\Models\Account\PtCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PtCategoryRepository extends BaseRepository
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

        return $this->paginateWithGenericData($query, $genericData);
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
