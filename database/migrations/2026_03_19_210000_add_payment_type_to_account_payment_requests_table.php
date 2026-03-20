<?php

use App\Constant\AccountPaymentTypeConstant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_payment_requests', function (Blueprint $table) {
            $table->string('payment_type', 20)->default(AccountPaymentTypeConstant::GCASH)->after('amount');
        });

        DB::table('account_payment_requests')->whereNull('payment_type')->update([
            'payment_type' => AccountPaymentTypeConstant::GCASH,
        ]);
    }

    public function down(): void
    {
        Schema::table('account_payment_requests', function (Blueprint $table) {
            $table->dropColumn('payment_type');
        });
    }
};
