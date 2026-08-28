<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Add the permanent public identifier used in the public registration URL
     * (/join/{publicCode}).
     *
     * This code is PERMANENT by design: gyms print its QR on tarpaulins and
     * posters, so it is never rotated or regenerated. Because of that, the
     * kioskRegistrationEnabled setting is the only kill switch for the link.
     * It is deliberately absent from Account::$fillable so it can never be
     * changed by mass assignment.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->ulid('public_code')->nullable()->unique()->after('referral_code');
        });

        $this->backfillPublicCodes();
    }

    /**
     * Assign a ULID to every existing account.
     *
     * The usleep is load-bearing, not a courtesy. Laravel's Str::ulid() wraps
     * Symfony's Ulid, which is MONOTONIC within a single millisecond: codes
     * generated in the same millisecond share the timestamp prefix and have
     * their random component merely incremented by one. A tight backfill loop
     * would therefore hand out consecutive, guessable codes, and one leaked
     * registration link would expose its neighbours. Sleeping past the
     * millisecond boundary forces a fresh 80 bits of randomness per row.
     *
     * @return void
     */
    private function backfillPublicCodes(): void
    {
        DB::table('accounts')->whereNull('public_code')->orderBy('id')
            ->chunkById(100, function ($accounts) {
                foreach ($accounts as $account) {
                    DB::table('accounts')
                        ->where('id', $account->id)
                        ->update(['public_code' => (string) Str::ulid()]);

                    usleep(1200);
                }
            });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['public_code']);
            $table->dropColumn('public_code');
        });
    }
};
