<?php

namespace App\Repositories\Common;

use App\Constant\ExpenseStatusConstant;
use App\Helpers\GenericData;
use App\Models\Common\Expense;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseRepository
{
    /**
     * Get all expenses for account_id
     *
     * @param GenericData $genericData
     * @return LengthAwarePaginator
     */
    public function getAllExpenses(GenericData $genericData): LengthAwarePaginator
    {
        $accountId = $genericData->userData->account_id;
        $query = Expense::where('account_id', $accountId);

        $filters = $genericData->filters ?? [];
        $dateFrom = $filters['dateFrom'] ?? $filters['date_from'] ?? null;
        $dateTo = $filters['dateTo'] ?? $filters['date_to'] ?? null;
        if ($dateFrom) {
            $query->where('expense_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('expense_date', '<=', $dateTo);
        }
        $genericData->filters = array_diff_key($filters, array_flip(['dateFrom', 'dateTo', 'date_from', 'date_to']));

        $genericData->applyRelations($query);
        $genericData->applyFilters($query);
        $genericData->applySorts($query);

        return $query->orderByDesc('expense_date')->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
    }

    /**
     * Count expenses for account within date range (for report export size check).
     *
     * @param int $accountId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return int
     */
    public function countByAccountAndDateRange(int $accountId, string $dateFrom, string $dateTo): int
    {
        return Expense::where('account_id', $accountId)
            ->where('expense_date', '>=', $dateFrom)
            ->where('expense_date', '<=', $dateTo)
            ->count();
    }

    /**
     * Get all expenses for account within date range for export (no pagination).
     *
     * @param int $accountId
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return Collection<int, Expense>
     */
    public function getForExport(int $accountId, string $dateFrom, string $dateTo): Collection
    {
        return Expense::where('account_id', $accountId)
            ->where('expense_date', '>=', $dateFrom)
            ->where('expense_date', '<=', $dateTo)
            ->with('category')
            ->orderByDesc('expense_date')
            ->get();
    }

    /**
     * Get expense by ID
     *
     * @param int $id
     * @param int $accountId
     * @return Expense
     */
    public function getExpenseById(int $id, int $accountId): Expense
    {
        return Expense::where('id', $id)
            ->where('account_id', $accountId)
            ->with('category')
            ->firstOrFail();
    }

    /**
     * Create a new expense
     *
     * @param GenericData $genericData
     * @return Expense
     */
    public function createExpense(GenericData $genericData): Expense
    {
        $data = $genericData->data;
        $data['account_id'] = $genericData->userData->account_id;
        return Expense::create($data);
    }

    /**
     * Update an expense
     *
     * @param int $id
     * @param GenericData $genericData
     * @return Expense
     */
    public function updateExpense(int $id, GenericData $genericData): Expense
    {
        $expense = $this->getExpenseById($id, $genericData->userData->account_id);
        $expense->update($genericData->data);
        return $expense->fresh(['category']);
    }

    /**
     * Post an expense (update status to POSTED)
     *
     * @param int $id
     * @param int $accountId
     * @return Expense
     */
    public function postExpense(int $id, int $accountId): Expense
    {
        $expense = $this->getExpenseById($id, $accountId);
        $expense->update(['status' => ExpenseStatusConstant::EXPENSE_STATUS_POSTED]);
        return $expense->fresh(['category']);
    }

    /**
     * Delete an expense (soft delete)
     *
     * @param int $id
     * @param int $accountId
     * @return bool
     */
    public function deleteExpense(int $id, int $accountId): bool
    {
        return Expense::where('id', $id)->where('account_id', $accountId)->delete();
    }
}

