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
        Schema::table('tb_customer_progress', function (Blueprint $table) {
            $table->foreignId('customer_scan_id')->nullable()->after('data_source')->constrained('tb_customer_scans')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_customer_progress', function (Blueprint $table) {
            $table->dropForeign(['customer_scan_id']);
            $table->dropColumn('customer_scan_id');
        });
    }
};
