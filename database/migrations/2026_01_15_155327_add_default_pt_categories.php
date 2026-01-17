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
        // Default PT categories matching frontend training styles
        $defaultCategories = [
            'Strength & Conditioning',
            'Cardio & Endurance',
            'Weight Loss Program',
            'HIIT Training',
            'Flexibility & Mobility',
            'CrossFit',
            'Yoga',
            'Pilates',
        ];

        // Insert default categories for account_id = 1
        foreach ($defaultCategories as $categoryName) {
            // Check if category already exists for this account
            $exists = DB::table('tb_pt_categories')
                ->where('account_id', 1)
                ->where('category_name', $categoryName)
                ->exists();

            if (!$exists) {
                DB::table('tb_pt_categories')->insert([
                    'account_id' => 1,
                    'category_name' => $categoryName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove default PT categories
        $defaultCategories = [
            'Strength & Conditioning',
            'Cardio & Endurance',
            'Weight Loss Program',
            'HIIT Training',
            'Flexibility & Mobility',
            'CrossFit',
            'Yoga',
            'Pilates',
        ];

        DB::table('tb_pt_categories')
            ->whereIn('category_name', $defaultCategories)
            ->where('account_id', 1)
            ->delete();
    }
};
