<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tb_customers', function (Blueprint $table) {
            $table->uuid('qr_code_uuid')->unique()->nullable()->after('id');
            $table->index('qr_code_uuid');
        });

        // Generate UUIDs for existing customers
        DB::table('tb_customers')->whereNull('qr_code_uuid')->chunkById(100, function ($customers) {
            foreach ($customers as $customer) {
                DB::table('tb_customers')
                    ->where('id', $customer->id)
                    ->update(['qr_code_uuid' => (string) Str::uuid()]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_customers', function (Blueprint $table) {
            $table->dropIndex(['qr_code_uuid']);
            $table->dropColumn('qr_code_uuid');
        });
    }
};
