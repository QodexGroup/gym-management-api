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
        Schema::create('tb_class_schedule', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('class_name');
            $table->text('description')->nullable();
            $table->foreignId('coach_id')->constrained('users')->onDelete('cascade');
            $table->string('class_type');
            $table->integer('capacity');
            $table->integer('duration')->comment('in minutes');
            $table->timestamp('start_date');
            $table->tinyInteger('schedule_type')->comment('1 for one time, 2 for recurring');
            $table->string('recurring_interval')->nullable();
            $table->integer('number_of_sessions')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_class_schedule');
    }
};
