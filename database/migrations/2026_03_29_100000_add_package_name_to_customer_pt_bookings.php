<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tb_customer_pt_bookings', function (Blueprint $table) {
            $table->string('package_name', 255)->nullable()->after('pt_package_id');
        });

        if (Schema::hasTable('tb_pt_packages')) {
            $rows = DB::table('tb_customer_pt_bookings')
                ->join('tb_pt_packages', 'tb_customer_pt_bookings.pt_package_id', '=', 'tb_pt_packages.id')
                ->whereNull('tb_customer_pt_bookings.package_name')
                ->select('tb_customer_pt_bookings.id', 'tb_pt_packages.package_name')
                ->get();

            foreach ($rows as $row) {
                DB::table('tb_customer_pt_bookings')
                    ->where('id', $row->id)
                    ->update(['package_name' => $row->package_name]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_customer_pt_bookings', function (Blueprint $table) {
            $table->dropColumn('package_name');
        });
    }
};
