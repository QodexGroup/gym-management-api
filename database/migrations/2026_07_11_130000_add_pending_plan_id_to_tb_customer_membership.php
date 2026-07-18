<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Holds a scheduled plan change for the "apply at next renewal" mode.
     * When set, the renewal job switches the membership to this plan and clears it.
     */
    public function up(): void
    {
        Schema::table('tb_customer_membership', function (Blueprint $table) {
            $table->unsignedBigInteger('pending_plan_id')->nullable()->after('membership_plan_id');

            $table->foreign('pending_plan_id')
                ->references('id')
                ->on('tb_membership_plan')
                ->onDelete('set null');

            $table->index('pending_plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_customer_membership', function (Blueprint $table) {
            $table->dropForeign(['pending_plan_id']);
            $table->dropIndex(['pending_plan_id']);
            $table->dropColumn('pending_plan_id');
        });
    }
};
