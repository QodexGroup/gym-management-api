<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tb_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('name');
            $table->timestamps();
        });

        // Insert default data
        $defaultCategories = [
            'Rent',
            'Salary',
            'Equipment',
            'Utilities',
            'Maintenance',
            'Supplies',
        ];

        foreach ($defaultCategories as $category) {
            DB::table('tb_expense_categories')->insert([
                'account_id' => 1,
                'name' => $category,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_expense_categories');
    }
};
