<?php

namespace App\Repositories\Core;

use App\Models\Core\Expense;
use Illuminate\Database\Eloquent\Collection;

class ExpenseRepository
{
    /**
     * Get all expenses for account_id = 1
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return Expense::where('account_id', 1)
            ->with('category')
            ->get();
    }

    /**
     * Create a new expense
     *
     * @param array $data
     * @return Expense
     */
    public function create(array $data): Expense
    {
        // Set account_id to 1 by default
        $data['account_id'] = 1;
        return Expense::create($data);
    }

    /**
     * Update an expense
     *
     * @param int $id
     * @param array $data
     * @return Expense
     */
    public function update(int $id, array $data): Expense
    {
        $expense = Expense::where('account_id', 1)->findOrFail($id);
        $expense->update($data);
        return $expense->fresh()->load('category');
    }

    /**
     * Delete an expense (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $expense = Expense::where('account_id', 1)->findOrFail($id);
        return $expense->delete();
    }
}

