<?php

use App\Http\Controllers\Account\MembershipPlanController;
use App\Http\Controllers\Core\CustomerController;
use App\Http\Controllers\Core\CustomerProgressController;
use App\Http\Controllers\Core\CustomerScanController;
use App\Http\Controllers\Core\CustomerFileController;
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

Route::prefix('customers')->group(function () {
    Route::get('/', [CustomerController::class, 'getCustomers']);
    Route::post('/', [CustomerController::class, 'store']);
    Route::get('/trainers', [CustomerController::class, 'getTrainers']);
    Route::get('/{id}', [CustomerController::class, 'getCustomer']);
    Route::put('/{id}', [CustomerController::class, 'updateCustomer']);
    Route::delete('/{id}', [CustomerController::class, 'delete']);

    // Customer Progress Routes
    Route::prefix('progress')->group(function () {
        Route::get('/{customerId}', [CustomerProgressController::class, 'getAllCustomerProgress']);
        Route::post('/{customerId}', [CustomerProgressController::class, 'createProgress']);
        Route::put('/{id}', [CustomerProgressController::class, 'updateProgress']);
        Route::delete('/{id}', [CustomerProgressController::class, 'deleteProgress']);

        // Progress File Routes - fileableType is automatically set to CustomerProgress
        Route::post('/{progressId}/files', [CustomerFileController::class, 'createProgressFile']);
    });

    // Customer Scan Routes
    Route::prefix('scans')->group(function () {
        Route::get('/{customerId}', [CustomerScanController::class, 'getAllCustomerScans']);
        Route::get('/{customerId}/type/{scanType}', [CustomerScanController::class, 'getScansByType']);
        Route::post('/{customerId}', [CustomerScanController::class, 'createScan']);
        Route::put('/{id}', [CustomerScanController::class, 'updateCustomerScan']);
        Route::delete('/{id}', [CustomerScanController::class, 'deleteCustomerScan']);

        // Scan File Routes - fileableType is automatically set to CustomerScans
        Route::post('/{scanId}/files', [CustomerFileController::class, 'createScanFile']);
    });

    // Customer File Routes (general)
    Route::prefix('files')->group(function () {
        Route::get('/{customerId}', [CustomerFileController::class, 'getFilesByCustomerId']);
        Route::delete('/{id}', [CustomerFileController::class, 'deleteFile']);
    });
});
