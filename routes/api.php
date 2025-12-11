<?php

use App\Http\Controllers\Account\MembershipPlanController;
use Illuminate\Support\Facades\Route;






Route::prefix('membership-plans')->group(function () {
    Route::get('/', [MembershipPlanController::class, 'getAllMembershipPlan']);
    Route::post('/', [MembershipPlanController::class, 'store']);
    Route::put('/{id}', [MembershipPlanController::class, 'updateMembershipPlan']);
    Route::delete('/{id}', [MembershipPlanController::class, 'delete']);
});

