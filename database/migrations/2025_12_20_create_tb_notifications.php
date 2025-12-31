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
        Schema::create('tb_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            
            // User ID is nullable for global notifications
            // When user management is implemented, uncomment the foreign key constraint
            $table->unsignedBigInteger('user_id')->nullable();
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->enum('type', [
                'membership_expiring',
                'payment_received',
                'customer_registered'
            ]);
            
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional context (customer_id, membership_id, etc.)
            
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('account_id');
            $table->index('user_id');
            $table->index('type');
            $table->index('read_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_notifications');
    }
};
