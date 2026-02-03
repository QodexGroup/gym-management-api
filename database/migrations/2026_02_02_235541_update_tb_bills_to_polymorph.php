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
        Schema::table('tb_customer_bills', function (Blueprint $table) {
            // Rename membership_plan_id to billable_id (already nullable)
            $table->renameColumn('membership_plan_id', 'billable_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_customer_bills', function (Blueprint $table) {
            // Rename billable_id back to membership_plan_id
            $table->renameColumn('billable_id', 'membership_plan_id');
        });
    }
};
