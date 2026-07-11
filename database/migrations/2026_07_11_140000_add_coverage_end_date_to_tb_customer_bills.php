<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * When set, paying this bill extends the membership to exactly this date
     * (instead of adding a full plan period). Used by prorated "bridge" bills
     * that align a member onto a fixed billing day without a coverage gap.
     */
    public function up(): void
    {
        Schema::table('tb_customer_bills', function (Blueprint $table) {
            $table->date('coverage_end_date')->nullable()->after('bill_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_customer_bills', function (Blueprint $table) {
            $table->dropColumn('coverage_end_date');
        });
    }
};
