<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')->whereNotNull('deleted_at')->update([
            'email' => null,
            'firebase_uid' => null,
        ]);

        $duplicateEmailCount = DB::table('users')
            ->whereNull('deleted_at')
            ->whereNotNull('email')
            ->select('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($duplicateEmailCount > 0) {
            throw new \RuntimeException(
                "Cannot add global unique email index: {$duplicateEmailCount} duplicate active email(s) exist across accounts. Resolve them before running this migration."
            );
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS users_email_account_unique');
            DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email)');

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_account_unique');
            $table->unique('email', 'users_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS users_email_unique');
            DB::statement('CREATE UNIQUE INDEX users_email_account_unique ON users (email, account_id)');

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->unique(['email', 'account_id'], 'users_email_account_unique');
        });
    }
};
