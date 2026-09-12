<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records WHICH console operator decided a payment request.
 *
 * `approved_by` is a GymHub user id, and a console operator does not have one —
 * they exist only in platform-console-api. Without this column the product can
 * show that a receipt was approved but not by whom, which is precisely the
 * question an audit asks first.
 *
 * Nullable because every existing row, and every decision made from inside
 * GymHub itself, legitimately has no console actor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_payment_requests', function (Blueprint $table) {
            $table->string('platform_actor', 191)->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('account_payment_requests', function (Blueprint $table) {
            $table->dropColumn('platform_actor');
        });
    }
};
