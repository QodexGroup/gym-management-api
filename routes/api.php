<?php

use App\Http\Controllers\Account\ClassScheduleController;
use App\Http\Controllers\Account\ClassScheduleSessionController;
use App\Http\Controllers\Account\MembershipPlanController;
use App\Http\Controllers\Account\PtCategoryController;
use App\Http\Controllers\Account\PtPackageController;
use App\Http\Controllers\Account\UsersController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Common\ExpenseCategoryController;
use App\Http\Controllers\Common\ExpenseController;
use App\Http\Controllers\Core\CustomerController;
use App\Http\Controllers\Core\CustomerProgressController;
use App\Http\Controllers\Core\CustomerScanController;
use App\Http\Controllers\Core\CustomerFileController;
use App\Http\Controllers\Core\CustomerBillController;
use App\Http\Controllers\Core\CustomerPaymentController;
use App\Http\Controllers\Core\ClassSessionBookingController;
use App\Http\Controllers\Core\DashboardController;
use App\Http\Controllers\Common\NotificationController;
use App\Http\Controllers\Core\CustomerPtPackageController;
use App\Http\Controllers\Core\PtBookingController;
use App\Http\Middleware\FirebaseAuthMiddleware;
use Illuminate\Support\Facades\Route;

// Auth routes (protected)
Route::middleware([FirebaseAuthMiddleware::class])->prefix('auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
});

// Dashboard routes (protected)
Route::middleware([FirebaseAuthMiddleware::class])->prefix('dashboard')->group(function () {
    Route::get('/stats', [DashboardController::class, 'getStats']);
});

Route::middleware([FirebaseAuthMiddleware::class])->group(function () {

    Route::prefix('users')->group(function () {
        Route::get('/', [UsersController::class, 'getAllUsers']);
        Route::get('/coaches', [UsersController::class, 'getCoaches']);
        Route::post('/', [UsersController::class, 'createUser']);
        Route::put('/{id}', [UsersController::class, 'updateUser']);
        Route::delete('/{id}', [UsersController::class, 'deleteUser']);
        Route::put('/{id}/deactivate', [UsersController::class, 'deactivateUser']);
        Route::put('/{id}/activate', [UsersController::class, 'activateUser']);
        Route::put('/{id}/reset-password', [UsersController::class, 'resetPassword']);
    });

    Route::prefix('membership-plans')->group(function () {
        Route::get('/', [MembershipPlanController::class, 'getAllMembershipPlan']);
        Route::post('/', [MembershipPlanController::class, 'store']);
        Route::put('/{id}', [MembershipPlanController::class, 'updateMembershipPlan']);
        Route::delete('/{id}', [MembershipPlanController::class, 'delete']);
    });

    Route::prefix('pt-packages')->group(function () {
        Route::get('/', [PtPackageController::class, 'getAllPtPackages']);
        Route::post('/', [PtPackageController::class, 'store']);
        Route::put('/{id}', [PtPackageController::class, 'updatePtPackage']);
        Route::delete('/{id}', [PtPackageController::class, 'delete']);
    });

    Route::prefix('pt-categories')->group(function () {
        Route::get('/', [PtCategoryController::class, 'getAllPtCategories']);
    });

    Route::prefix('class-schedules')->group(function () {
        Route::get('/', [ClassScheduleController::class, 'getAllClassSchedules']);
        Route::get('/my-schedules', [ClassScheduleController::class, 'getMyClassSchedules']);
        Route::get('/coach/{coachId}', [ClassScheduleController::class, 'getClassSchedulesByCoachId']);
        Route::post('/', [ClassScheduleController::class, 'store']);
        Route::put('/{id}', [ClassScheduleController::class, 'updateClassSchedule']);
        Route::delete('/{id}', [ClassScheduleController::class, 'delete']);
    });

    Route::prefix('class-schedule-sessions')->group(function () {
        Route::get('/', [ClassScheduleSessionController::class, 'getAllSessions']);
        Route::put('/{id}', [ClassScheduleSessionController::class, 'updateSession']);
    });

    Route::prefix('class-session-bookings')->group(function () {
        Route::get('/', [ClassSessionBookingController::class, 'getBookingSessions']);
        Route::post('/', [ClassSessionBookingController::class, 'bookSession']);
        Route::get('/{bookingId}', [ClassSessionBookingController::class, 'getBookingById']);
        Route::put('/{bookingId}', [ClassSessionBookingController::class, 'updateBooking']);
        Route::get('/session/{sessionId}', [ClassSessionBookingController::class, 'getBookingsBySession']);
        Route::put('/{bookingId}/attendance', [ClassSessionBookingController::class, 'updateAttendanceStatus']);
        Route::put('/session/{sessionId}/mark-all-attended', [ClassSessionBookingController::class, 'markAllAsAttended']);
    });

    Route::prefix('pt-bookings')->group(function () {
        Route::get('/', [PtBookingController::class, 'getPtBookings']);
        Route::get('/coach/{coachId}', [PtBookingController::class, 'getCoachPtBookings']);
        Route::get('/session/{sessionId}', [PtBookingController::class, 'getPtBookingsBySession']);
        Route::post('/', [PtBookingController::class, 'create']);
        Route::put('/{id}', [PtBookingController::class, 'update']);
        Route::put('/{id}/cancel', [PtBookingController::class, 'markAsCancelled']);
        Route::put('/{id}/attend', [PtBookingController::class, 'markAsAttended']);
        Route::put('/{id}/no-show', [PtBookingController::class, 'markAsNoShow']);
    });

    Route::prefix('expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'getAllExpenses']);
        Route::get('/{id}', [ExpenseController::class, 'getExpenseById']);
        Route::post('/', [ExpenseController::class, 'createExpense']);
        Route::put('/{id}', [ExpenseController::class, 'updateExpense']);
        Route::put('/{id}/post', [ExpenseController::class, 'postExpense']);
        Route::delete('/{id}', [ExpenseController::class, 'deleteExpense']);
    });

    Route::prefix('expense-categories')->group(function () {
        Route::get('/', [ExpenseCategoryController::class, 'getAllExpenseCategories']);
        Route::get('/{id}', [ExpenseCategoryController::class, 'getCategoryById']);
        Route::post('/', [ExpenseCategoryController::class, 'createCategory']);
        Route::put('/{id}', [ExpenseCategoryController::class, 'updateCategory']);
        Route::delete('/{id}', [ExpenseCategoryController::class, 'deleteCategory']);
    });

    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'getCustomers']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::get('/{id}', [CustomerController::class, 'getCustomer']);
        Route::put('/{id}', [CustomerController::class, 'updateCustomer']);
        Route::delete('/{id}', [CustomerController::class, 'delete']);
        Route::post('/{id}/membership', [CustomerController::class, 'createOrUpdateMembership']);
        Route::post('/{id}/pt-packages', [CustomerController::class, 'createPtPackage']);
        Route::get('/{id}/pt-packages', [CustomerPtPackageController::class, 'getPtPackages']);

        Route::delete('/pt-packages/{ptPackageId}', [CustomerPtPackageController::class, 'removePtPackage']);

        // Customer Progress Routes
        Route::prefix('progress')->group(function () {
            Route::get('/', [CustomerProgressController::class, 'getAllCustomerProgress']);
            Route::post('/', [CustomerProgressController::class, 'createProgress']);
            Route::put('/{id}', [CustomerProgressController::class, 'updateProgress']);
            Route::delete('/{id}', [CustomerProgressController::class, 'deleteProgress']);

            // Progress File Routes - fileableType is automatically set to CustomerProgress
            Route::post('/{progressId}/files', [CustomerFileController::class, 'createProgressFile']);
        });

        // Customer Scan Routes
        Route::prefix('scans')->group(function () {
            Route::get('/', [CustomerScanController::class, 'getAllCustomerScans']);
            Route::get('/type/{scanType}', [CustomerScanController::class, 'getScansByType']);
            Route::post('/', [CustomerScanController::class, 'createScan']);
            Route::put('/{id}', [CustomerScanController::class, 'updateCustomerScan']);
            Route::delete('/{id}', [CustomerScanController::class, 'deleteCustomerScan']);

            // Scan File Routes - fileableType is automatically set to CustomerScans
            Route::post('/{scanId}/files', [CustomerFileController::class, 'createScanFile']);
        });

        // Customer File Routes (general)
        Route::prefix('files')->group(function () {
            Route::get('/', [CustomerFileController::class, 'getFilesByCustomerId']);
            Route::delete('/{id}', [CustomerFileController::class, 'deleteFile']);
        });

        // Customer Bill Routes
        Route::prefix('bills')->group(function () {
            Route::get('/', [CustomerBillController::class, 'getCustomerBills']);
            Route::post('/', [CustomerBillController::class, 'createBill']);
            Route::put('/{id}', [CustomerBillController::class, 'updateBill']);
            Route::delete('/{id}', [CustomerBillController::class, 'delete']);

            // Customer Payment Routes
            Route::get('/{billId}/payments', [CustomerPaymentController::class, 'getBillPayments']);
            Route::post('/{billId}/payments', [CustomerPaymentController::class, 'addPayment']);
            Route::delete('/payments/{id}', [CustomerPaymentController::class, 'deletePayment']);
        });

    });
});

Route::prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
});

Route::prefix('notification-preferences')->group(function () {
    Route::get('/', [\App\Http\Controllers\NotificationPreferenceController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\NotificationPreferenceController::class, 'update']);
});

