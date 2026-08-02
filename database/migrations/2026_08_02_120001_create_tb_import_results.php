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
        Schema::create('tb_import_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_job_id')->index();
            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedInteger('row_number');
            $table->string('status'); // success | failed | skipped
            $table->json('original_data')->nullable();
            $table->json('errors')->nullable();
            $table->unsignedBigInteger('created_record_id')->nullable();
            $table->string('message')->nullable();
            $table->timestamps();

            $table->foreign('import_job_id')->references('id')->on('tb_import_jobs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_import_results');
    }
};
