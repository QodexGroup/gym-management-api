<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Denormalise a pointer to each customer's current membership.
     *
     * Before this, "the current membership" was resolved at query time with a
     * correlated subquery over the whole membership table, which the client
     * list and its stat cards both paid for on every request. The pointer turns
     * that into a primary-key join and makes the "latest membership" ordering
     * rule live in exactly one place (CustomerMembershipObserver).
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('tb_customers', function (Blueprint $table) {
            $table->unsignedBigInteger('current_membership_id')->nullable()->after('account_id');

            $table->foreign('current_membership_id')
                ->references('id')
                ->on('tb_customer_membership')
                ->onDelete('set null');

            $table->index('current_membership_id');
        });

        // Backfill with the same ordering Customer::currentMembership() used
        // before this change. `id desc` is only a tie-breaker: it makes the
        // previously undefined "same start date and created_at" case
        // deterministic, and matches CustomerMembershipObserver.
        DB::statement(<<<'SQL'
            UPDATE tb_customers AS c
            SET c.current_membership_id = (
                SELECT m.id
                FROM tb_customer_membership AS m
                WHERE m.customer_id = c.id
                  AND m.deleted_at IS NULL
                ORDER BY m.membership_start_date DESC, m.created_at DESC, m.id DESC
                LIMIT 1
            )
        SQL);
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('tb_customers', function (Blueprint $table) {
            $table->dropForeign(['current_membership_id']);
            $table->dropIndex(['current_membership_id']);
            $table->dropColumn('current_membership_id');
        });
    }
};
