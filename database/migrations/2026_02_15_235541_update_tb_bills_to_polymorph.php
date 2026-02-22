<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('tb_customer_bills')) {
            // Check if billable_id doesn't exist and membership_plan_id exists
            if (!Schema::hasColumn('tb_customer_bills', 'billable_id') && Schema::hasColumn('tb_customer_bills', 'membership_plan_id')) {
                Schema::table('tb_customer_bills', function (Blueprint $table) {
                    // Rename membership_plan_id to billable_id (already nullable)
                    $table->renameColumn('membership_plan_id', 'billable_id');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tb_customer_bills') && Schema::hasColumn('tb_customer_bills', 'billable_id')) {
            Schema::table('tb_customer_bills', function (Blueprint $table) {
                $table->renameColumn('billable_id', 'membership_plan_id');
            });
        }
    }
};
