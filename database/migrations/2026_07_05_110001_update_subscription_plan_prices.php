<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Update platform subscription plan prices.
     * Monthly base ₱800, Quarterly ₱2,300, Yearly ₱8,800.
     */
    private array $prices = [
        'monthly-subscription' => 800,
        'quarterly-subscription' => 2300,
        'yearly-subscription' => 8800,
    ];

    private array $previousPrices = [
        'monthly-subscription' => 1200,
        'quarterly-subscription' => 3400,
        'yearly-subscription' => 13000,
    ];

    public function up(): void
    {
        foreach ($this->prices as $slug => $price) {
            DB::table('subscription_plans')
                ->where('slug', $slug)
                ->update(['price' => $price, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach ($this->previousPrices as $slug => $price) {
            DB::table('subscription_plans')
                ->where('slug', $slug)
                ->update(['price' => $price, 'updated_at' => now()]);
        }
    }
};
