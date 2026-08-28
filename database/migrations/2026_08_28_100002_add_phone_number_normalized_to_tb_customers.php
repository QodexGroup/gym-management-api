<?php

use App\Helpers\PhoneNumberHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the canonical form of each member's phone number.
     *
     * The public registration endpoint rejects a duplicate phone number, which
     * only works if "0917-123-4567", "+63 917 123 4567" and "9171234567" all
     * compare equal. That comparison lived in SQL as a REPLACE() chain, which
     * duplicated PhoneNumberHelper's rules, silently disagreed with it (dots and
     * bare 10-digit numbers were never matched, so duplicates got through), and
     * could not use an index.
     *
     * Normalising on write instead makes the helper the single source of truth
     * and turns the check into one indexed lookup.
     */
    public function up(): void
    {
        Schema::table('tb_customers', function (Blueprint $table) {
            $table->string('phone_number_normalized', 20)->nullable()->after('phone_number');
            $table->index(['account_id', 'phone_number_normalized']);
        });

        $this->backfill();
    }

    /**
     * Populate the column for existing members using the same helper the
     * application uses, so old and new rows are directly comparable.
     *
     * @return void
     */
    private function backfill(): void
    {
        DB::table('tb_customers')
            ->select('id', 'phone_number')
            ->orderBy('id')
            ->chunkById(500, function ($customers) {
                foreach ($customers as $customer) {
                    $normalized = PhoneNumberHelper::normalize($customer->phone_number);

                    DB::table('tb_customers')
                        ->where('id', $customer->id)
                        ->update(['phone_number_normalized' => $normalized === '' ? null : $normalized]);
                }
            });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('tb_customers', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'phone_number_normalized']);
            $table->dropColumn('phone_number_normalized');
        });
    }
};
