<?php

use App\Http\Controllers\Account\MembershipPlanController;
use App\Http\Controllers\Core\ExpenseController;
use App\Http\Controllers\Core\ExpenseCategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('membership-plans')->group(function () {
    Route::get('/', [MembershipPlanController::class, 'getAllMembershipPlan']);
    Route::post('/', [MembershipPlanController::class, 'store']);
    Route::put('/{id}', [MembershipPlanController::class, 'updateMembershipPlan']);
    Route::delete('/{id}', [MembershipPlanController::class, 'delete']);
});

Route::prefix('expenses')->group(function () {
    Route::get('/', [ExpenseController::class, 'getAllExpenses']);
    Route::post('/', [ExpenseController::class, 'store']);
    Route::put('/{id}', [ExpenseController::class, 'updateExpense']);
    Route::delete('/{id}', [ExpenseController::class, 'delete']);
});

Route::prefix('expense-categories')->group(function () {
    Route::get('/', [ExpenseCategoryController::class, 'getAllExpenseCategories']);
    Route::post('/', [ExpenseCategoryController::class, 'store']);
    Route::put('/{id}', [ExpenseCategoryController::class, 'updateExpenseCategory']);
    Route::delete('/{id}', [ExpenseCategoryController::class, 'delete']);
});
