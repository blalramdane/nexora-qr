<?php

use App\Support\Deployment\SchemaSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        SchemaSync::createTable('prospect_menu_researches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_business_id')->constrained('local_businesses')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('links_found')->index();
            $table->string('source_website_url', 1000);
            $table->json('discovered_links')->nullable();
            $table->string('selected_menu_url', 1000)->nullable();
            $table->string('source_content_type', 120)->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->json('extracted_data')->nullable();
            $table->json('reviewed_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['local_business_id', 'status', 'created_at'], 'prospect_menu_research_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_menu_researches');
    }
};
