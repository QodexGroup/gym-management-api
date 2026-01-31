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
        Schema::create('tb_customer_pt_package', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->foreignId('customer_id')->constrained('tb_customer');
            $table->foreignId('pt_package_id')->constrained('tb_pt_packages');
            $table->unsignedBigInteger('coach_id');
            $table->date('start_date');
            $table->string('status')->default('active');
            $table->tinyInteger('number_of_sessions_remaining')->default(0)->comment('number of sessions remaining');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('tb_users')->onDelete('cascade');

            $table->index('customer_id');
            $table->index('pt_package_id');
            $table->index('coach_id');
            $table->index('status');
            $table->index('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_customer_pt_package');
    }
};
