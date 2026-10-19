<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_scan_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('uploaded'); // uploaded, processing, extracted, reviewed, imported, failed
            $table->json('image_paths')->nullable(); // uploaded image paths
            $table->longText('raw_ocr_text')->nullable();
            $table->json('extracted_data')->nullable(); // structured extraction result
            $table->json('reviewed_data')->nullable(); // user-corrected data
            $table->json('import_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_scan_jobs');
    }
};
