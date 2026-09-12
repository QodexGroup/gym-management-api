<?php

use App\Http\Controllers\Platform\PlatformAccountController;
use App\Http\Controllers\Platform\PlatformCommandController;
use App\Http\Controllers\Platform\PlatformHealthController;
use App\Http\Controllers\Platform\PlatformInvoiceController;
use App\Http\Controllers\Platform\PlatformPaymentRequestController;
use App\Http\Controllers\Platform\PlatformSummaryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform Admin Contract (v1)
|--------------------------------------------------------------------------
|
| The machine-to-machine surface the operations console calls. It lives in its
| own file, not in routes/api.php, because it belongs to a different consumer:
| a change the console needs is made here and cannot disturb a gym-facing
| route, and a 404 or a 405 tells you which of the two surfaces broke before
| you open anything.
|
| Registered in bootstrap/app.php with the SAME `api` middleware group and
| `api` prefix Laravel gives routes/api.php, so every URL is unchanged:
| /api/platform/*. Moving a route out of this file changes its URL.
|
| Deliberately OUTSIDE the Firebase group in routes/api.php: the caller is
| another server holding a service token, not a signed-in gym user, and these
| reads span every account rather than being scoped to one. That makes the
| service token the entire security boundary — see
| PlatformServiceTokenMiddleware.
|
*/
Route::prefix('platform')->middleware(['platform.service'])->group(function () {
    Route::get('/health', [PlatformHealthController::class, 'getHealth']);
    Route::get('/summary', [PlatformSummaryController::class, 'getSummary']);

    Route::get('/accounts', [PlatformAccountController::class, 'getAccounts']);
    Route::get('/accounts/{accountId}', [PlatformAccountController::class, 'getAccountDetail'])
        ->whereNumber('accountId');

    Route::get('/invoices', [PlatformInvoiceController::class, 'getInvoices']);

    // Maintenance commands. The LIST is a plain read; RUNNING one is guarded by
    // the same idempotency middleware as a payment decision, because a
    // double-submitted "generate invoices" would bill everyone twice.
    Route::get('/commands', [PlatformCommandController::class, 'getCommands']);
    Route::get('/payment-requests', [PlatformPaymentRequestController::class, 'getPaymentRequests']);

    // Decisions are idempotent: a retried or double-clicked approve must not
    // settle the same invoice twice and extend coverage twice.
    Route::middleware(['idempotent'])->group(function () {
        Route::post('/payment-requests/{paymentRequestId}/approve', [PlatformPaymentRequestController::class, 'approvePaymentRequest'])
            ->whereNumber('paymentRequestId');
        Route::post('/payment-requests/{paymentRequestId}/reject', [PlatformPaymentRequestController::class, 'rejectPaymentRequest'])
            ->whereNumber('paymentRequestId');

        Route::post('/commands/{commandKey}/run', [PlatformCommandController::class, 'runCommand'])
            ->where('commandKey', '[A-Za-z0-9._-]+');
    });
});
