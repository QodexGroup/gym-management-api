<?php

namespace App\Repositories\Core;

use App\Models\Core\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;

class ExpenseCategoryRepository
{
    /**
     * Get all expense categories for account_id = 1
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return ExpenseCategory::where('account_id', 1)->get();
    }

    /**
     * Create a new expense category
     *
     * @param array $data
     * @return ExpenseCategory
     */
    public function create(array $data): ExpenseCategory
    {
        // Set account_id to 1 by default
        $data['account_id'] = 1;
        return ExpenseCategory::create($data);
    }

    /**
     * Update an expense category
     *
     * @param int $id
     * @param array $data
     * @return ExpenseCategory
     */
    public function update(int $id, array $data): ExpenseCategory
    {
        $category = ExpenseCategory::where('account_id', 1)->findOrFail($id);
        $category->update($data);
        return $category->fresh();
    }

    /**
     * Delete an expense category
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $category = ExpenseCategory::where('account_id', 1)->findOrFail($id);
        return $category->delete();
    }
}

