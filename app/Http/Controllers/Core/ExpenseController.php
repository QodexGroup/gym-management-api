<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\ExpenseRequest;
use App\Http\Resources\Core\ExpenseResource;
use App\Repositories\Core\ExpenseRepository;
use Illuminate\Http\JsonResponse;

class ExpenseController extends Controller
{
    public function __construct(
        private ExpenseRepository $repository
    ) {
    }

    /**
     * Get all expenses by account_id
     *
     * @return JsonResponse
     */
    public function getAllExpenses(): JsonResponse
    {
        $expenses = $this->repository->getAll();
        return ApiResponse::success(ExpenseResource::collection($expenses));
    }

    /**
     * Create a new expense
     *
     * @param ExpenseRequest $request
     * @return JsonResponse
     */
    public function store(ExpenseRequest $request): JsonResponse
    {
        $data = $request->validated();
        // Map camelCase to snake_case
        $data['category_id'] = $data['categoryId'];
        $data['expense_date'] = $data['expenseDate'];
        unset($data['categoryId'], $data['expenseDate']);

        $expense = $this->repository->create($data);
        return ApiResponse::success(new ExpenseResource($expense->load('category')), 'Expense created successfully', 201);
    }

    /**
     * Update an expense
     *
     * @param ExpenseRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateExpense(ExpenseRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        // Map camelCase to snake_case
        $data['category_id'] = $data['categoryId'];
        $data['expense_date'] = $data['expenseDate'];
        unset($data['categoryId'], $data['expenseDate']);

        $expense = $this->repository->update($id, $data);
        return ApiResponse::success(new ExpenseResource($expense), 'Expense updated successfully');
    }

    /**
     * Delete an expense (soft delete)
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $this->repository->delete($id);
        return ApiResponse::success(null, 'Expense deleted successfully');
    }
}

