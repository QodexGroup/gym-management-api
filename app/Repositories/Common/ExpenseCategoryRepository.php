<?php

namespace App\Repositories\Common;

use App\Helpers\GenericData;
use App\Models\Common\ExpenseCategory;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseCategoryRepository
{
    /**
     * Get all expense categories for account_id
     *
     * @param GenericData $genericData
     * @return LengthAwarePaginator
     */
    public function getAllCategories(GenericData $genericData): LengthAwarePaginator
    {
        $accountId = $genericData->userData->account_id;
        $query = ExpenseCategory::where('account_id', $accountId);

        $genericData->applyRelations($query);
        $genericData->applyFilters($query);
        $genericData->applySorts($query);

        return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
    }

    /**
     * Get category by ID
     *
     * @param int $id
     * @param int $accountId
     * @return ExpenseCategory
     */
    public function getCategoryById(int $id, int $accountId): ExpenseCategory
    {
        return ExpenseCategory::where('id', $id)
            ->where('account_id', $accountId)
            ->firstOrFail();
    }

    /**
     * Create a new expense category
     *
     * @param GenericData $genericData
     * @return ExpenseCategory
     */
    public function createCategory(GenericData $genericData): ExpenseCategory
    {
        $data = $genericData->data;
        $data['account_id'] = $genericData->userData->account_id;
        return ExpenseCategory::create($data);
    }

    /**
     * Update an expense category
     *
     * @param int $id
     * @param GenericData $genericData
     * @return ExpenseCategory
     */
    public function updateCategory(int $id, GenericData $genericData): ExpenseCategory
    {
        $category = $this->getCategoryById($id, $genericData->userData->account_id);
        $category->update($genericData->data);
        return $category->fresh();
    }

    /**
     * Delete an expense category
     *
     * @param int $id
     * @param int $accountId
     * @return bool
     */
    public function deleteCategory(int $id, int $accountId): bool
    {
        return ExpenseCategory::where('id', $id)->where('account_id', $accountId)->delete();
    }
}

