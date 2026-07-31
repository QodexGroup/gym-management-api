<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a receipt_url column to tb_customer_payment. Stores the R2 object path
 * (not a full URL) of an optional payment receipt, matching how the expense
 * receipt (tb_expenses.receipt_url) and customer photo columns work.
 */
return new class extends Migration
{
    /**
     * Add receipt_url to tb_customer_payment.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('tb_customer_payment', function (Blueprint $table) {
            $table->string('receipt_url', 500)->nullable()->after('remarks');
        });
    }

    /**
     * Drop the receipt_url column.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('tb_customer_payment', function (Blueprint $table) {
            $table->dropColumn('receipt_url');
        });
    }
};
