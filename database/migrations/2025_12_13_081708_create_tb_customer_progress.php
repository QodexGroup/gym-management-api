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
        Schema::create('tb_customer_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->default(1); // Skip foreign constraint for now
            $table->foreignId('customer_id')->constrained('tb_customers')->onDelete('cascade');
            $table->unsignedBigInteger('recorded_by')->nullable(); // Skip foreign constraint for now

            // Basic Measurements
            $table->decimal('weight', 5, 2)->nullable(); // kg
            $table->decimal('height', 5, 2)->nullable(); // cm
            $table->decimal('body_fat_percentage', 5, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();

            // Body Measurements (cm)
            $table->decimal('chest', 5, 2)->nullable();
            $table->decimal('waist', 5, 2)->nullable();
            $table->decimal('hips', 5, 2)->nullable();
            $table->decimal('left_arm', 5, 2)->nullable();
            $table->decimal('right_arm', 5, 2)->nullable();
            $table->decimal('left_thigh', 5, 2)->nullable();
            $table->decimal('right_thigh', 5, 2)->nullable();
            $table->decimal('left_calf', 5, 2)->nullable();
            $table->decimal('right_calf', 5, 2)->nullable();

            // Body Composition (InBody/Styku data)
            $table->decimal('skeletal_muscle_mass', 5, 2)->nullable(); // kg
            $table->decimal('body_fat_mass', 5, 2)->nullable(); // kg
            $table->decimal('total_body_water', 5, 2)->nullable(); // L
            $table->decimal('protein', 5, 2)->nullable(); // kg
            $table->decimal('minerals', 5, 2)->nullable(); // kg
            $table->decimal('visceral_fat_level', 5, 2)->nullable();
            $table->decimal('basal_metabolic_rate', 8, 2)->nullable(); // kcal

            // Data Source
            $table->enum('data_source', ['manual', 'inbody', 'styku'])->default('manual');

            // Notes
            $table->text('notes')->nullable();

            $table->date('recorded_date');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'recorded_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_customer_progress');
    }
};
