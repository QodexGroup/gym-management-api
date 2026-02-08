<?php

namespace App\Http\Controllers\Common;

use App\Helpers\ApiResponse;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\Common\ExpenseRequest;
use App\Http\Resources\Common\ExpenseResource;
use App\Repositories\Common\ExpenseRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class ExpenseController
{
    public function __construct(
        private ExpenseRepository $expenseRepository,
    )
    {}

    /**
     * Get all expenses by account_id
     *
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getAllExpenses(GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericData();
        $expenses = $this->expenseRepository->getAllExpenses($genericData);
        return ApiResponse::success(ExpenseResource::collection($expenses)->response()->getData(true));
    }

    /**
     * Get a specific expense by ID
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function getExpenseById(GenericRequest $request, $id): JsonResponse
    {
        $genericData = $request->getGenericData();
        $expense = $this->expenseRepository->getExpenseById((int)$id, $genericData->userData->account_id);
        return ApiResponse::success(new ExpenseResource($expense));
    }

    /**
     * Create a new expense
     *
     * @param ExpenseRequest $request
     * @return JsonResponse
     */
    public function createExpense(ExpenseRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $expense = $this->expenseRepository->createExpense($genericData);
        return ApiResponse::success(new ExpenseResource($expense->load('category')), 'Expense created successfully', 201);
    }

    /**
     * Update an expense
     *
     * @param ExpenseRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateExpense($id, ExpenseRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $expense = $this->expenseRepository->updateExpense((int)$id, $genericData);
        return ApiResponse::success(new ExpenseResource($expense), 'Expense updated successfully');
    }

    /**
     * Post an expense (update status to POSTED)
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function postExpense(GenericRequest $request, $id): JsonResponse
    {
        try {
            $genericData = $request->getGenericData();
            $expense = $this->expenseRepository->postExpense((int)$id, $genericData->userData->account_id);
            return ApiResponse::success(new ExpenseResource($expense), 'Expense posted successfully');
        } catch (\Throwable $th) {
            return ApiResponse::error('Failed to post expense: ' . $th->getMessage(), 500);
        }
    }

    /**
     * Delete an expense (soft delete)
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function deleteExpense(GenericRequest $request, $id): JsonResponse
    {
        try {
            $genericData = $request->getGenericData();
            $this->expenseRepository->deleteExpense((int)$id, $genericData->userData->account_id);
            return ApiResponse::success(null, 'Expense deleted successfully');
        } catch (\Throwable $th) {
            return ApiResponse::error('Failed to delete expense: ' . $th->getMessage(), 500);
        }
    }
}

