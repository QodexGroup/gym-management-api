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
        Schema::create('tb_customer_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->default(1); // Skip foreign constraint for now
            $table->foreignId('customer_id')->constrained('tb_customers')->onDelete('cascade');

            // Polymorphic relationship - links to progress_tracking, inbody_scan, or styku_scan
            $table->string('fileable_type');
            $table->unsignedBigInteger('fileable_id');
            $table->string('remarks');
            // File Details
            $table->string('file_name', 255);
            $table->string('file_url', 500); // Firebase Storage URL (stores path: {accountId}/{customerId}/filename)
            $table->string('thumbnail_url', 500)->nullable(); // Thumbnail for faster loading (mainly for photos)
            $table->decimal('file_size', 10, 2)->nullable(); // File size in KB (numeric for storage calculations)
            $table->string('mime_type', 100)->nullable();
            $table->date('file_date'); // Date the file was taken/created
            $table->unsignedBigInteger('uploaded_by')->nullable(); // Skip foreign constraint for now

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'remarks']);
            $table->index(['fileable_type', 'fileable_id']); // Polymorphic index
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_customer_files');
    }
};
