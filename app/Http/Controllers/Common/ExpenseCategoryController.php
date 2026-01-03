<?php

namespace App\Http\Controllers\Common;

use App\Helpers\ApiResponse;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\Common\ExpenseCategoryRequest;
use App\Http\Resources\Common\ExpenseCategoryResource;
use App\Repositories\Common\ExpenseCategoryRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class ExpenseCategoryController
{
    public function __construct(
        private ExpenseCategoryRepository $expenseCategoryRepository,
    )
    {}

    /**
     * Get all expense categories by account_id
     *
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getAllExpenseCategories(GenericRequest $request): JsonResponse
    {
        $genericData = $request->getGenericData();
        $categories = $this->expenseCategoryRepository->getAllCategories($genericData);
        return ApiResponse::success($categories);
    }

    /**
     * Get a specific expense category by ID
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function getCategoryById(GenericRequest $request, $id): JsonResponse
    {
        $genericData = $request->getGenericData();
        $category = $this->expenseCategoryRepository->getCategoryById((int)$id, $genericData->userData->account_id);
        return ApiResponse::success(new ExpenseCategoryResource($category));
    }

    /**
     * Create a new expense category
     *
     * @param ExpenseCategoryRequest $request
     * @return JsonResponse
     */
    public function createCategory(ExpenseCategoryRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $category = $this->expenseCategoryRepository->createCategory($genericData);
        return ApiResponse::success(new ExpenseCategoryResource($category), 'Expense category created successfully', 201);
    }

    /**
     * Update an expense category
     *
     * @param ExpenseCategoryRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateCategory($id, ExpenseCategoryRequest $request): JsonResponse
    {
        $genericData = $request->getGenericDataWithValidated();
        $category = $this->expenseCategoryRepository->updateCategory((int)$id, $genericData);
        return ApiResponse::success(new ExpenseCategoryResource($category), 'Expense category updated successfully');
    }

    /**
     * Delete an expense category
     *
     * @param GenericRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function deleteCategory(GenericRequest $request, $id): JsonResponse
    {
        try {
            $genericData = $request->getGenericData();
            $this->expenseCategoryRepository->deleteCategory((int)$id, $genericData->userData->account_id);
            return ApiResponse::success(null, 'Expense category deleted successfully');
        } catch (\Throwable $th) {
            return ApiResponse::error('Failed to delete expense category: ' . $th->getMessage(), 500);
        }
    }
}

