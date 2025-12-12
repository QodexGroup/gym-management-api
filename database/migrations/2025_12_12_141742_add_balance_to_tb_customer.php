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
        Schema::table('tb_customers', function (Blueprint $table) {
            $table->decimal('balance', 10, 2)->default(0)->after('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_customers', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
