<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\ExpenseCategoryRequest;
use App\Http\Resources\Core\ExpenseCategoryResource;
use App\Repositories\Core\ExpenseCategoryRepository;
use Illuminate\Http\JsonResponse;

class ExpenseCategoryController extends Controller
{
    public function __construct(
        private ExpenseCategoryRepository $repository
    ) {
    }

    /**
     * Get all expense categories by account_id
     *
     * @return JsonResponse
     */
    public function getAllExpenseCategories(): JsonResponse
    {
        $categories = $this->repository->getAll();
        return ApiResponse::success(ExpenseCategoryResource::collection($categories));
    }

    /**
     * Create a new expense category
     *
     * @param ExpenseCategoryRequest $request
     * @return JsonResponse
     */
    public function store(ExpenseCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $category = $this->repository->create($data);
        return ApiResponse::success(new ExpenseCategoryResource($category), 'Expense category created successfully', 201);
    }

    /**
     * Update an expense category
     *
     * @param ExpenseCategoryRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateExpenseCategory(ExpenseCategoryRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        $category = $this->repository->update($id, $data);
        return ApiResponse::success(new ExpenseCategoryResource($category), 'Expense category updated successfully');
    }

    /**
     * Delete an expense category
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $this->repository->delete($id);
        return ApiResponse::success(null, 'Expense category deleted successfully');
    }
}

