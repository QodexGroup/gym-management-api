<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The referral discount value reuses the existing account_invoices.discount_amount column.
     * This flag marks an invoice whose discount originated from the referral program, for
     * auditing/reporting and to keep the "one referral discount per invoice" rule visible.
     */
    public function up(): void
    {
        Schema::table('account_invoices', function (Blueprint $table) {
            $table->boolean('referral_discount_applied')->default(false)->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('account_invoices', function (Blueprint $table) {
            $table->dropColumn('referral_discount_applied');
        });
    }
};
