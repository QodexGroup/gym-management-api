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
        Schema::create('tb_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('category_id');
            $table->text('description');
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->enum('status', ['POSTED', 'UNPOSTED'])->default('UNPOSTED');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('tb_expense_categories')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_expenses');
    }
};
