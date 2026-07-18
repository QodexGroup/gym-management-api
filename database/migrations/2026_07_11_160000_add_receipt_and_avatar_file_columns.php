<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File reference columns for the receipt/avatar uploads. Each stores the R2
 * object path (not a full URL), matching how tb_customer_files.file_url works.
 * Customer photos already have tb_customers.photo, so only expenses and users
 * need new columns.
 */
return new class extends Migration
{
    /**
     * Add receipt_url to tb_expenses and avatar to tb_users.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('tb_expenses', function (Blueprint $table) {
            $table->string('receipt_url', 500)->nullable()->after('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar', 500)->nullable();
        });
    }

    /**
     * Drop the added columns.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('tb_expenses', function (Blueprint $table) {
            $table->dropColumn('receipt_url');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
};
