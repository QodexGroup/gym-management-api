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
        Schema::create('tb_customer_scans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->default(1); // Skip foreign constraint for now
            $table->foreignId('customer_id')->constrained('tb_customers')->onDelete('cascade');
            $table->unsignedBigInteger('uploaded_by')->nullable(); // Skip foreign constraint for now
            // Scan Type
            $table->enum('scan_type', ['inbody', 'styku'])->index();
            // Scan Date
            $table->date('scan_date');
            // Notes
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'scan_type']);
            $table->index(['customer_id', 'scan_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_customer_scans');
    }
};

