<?php

use App\Support\Deployment\SchemaSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent guard: survive an earlier partially-recorded run.
        SchemaSync::alterTable('local_businesses', function (Blueprint $table) {
            $table->string('lead_status')->default('needs_verification')->index();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_action_at')->nullable()->index();
            $table->string('contact_channel')->nullable();
            $table->text('evidence_notes')->nullable();
            $table->string('menu_rights_status')->default('not_checked');
        });
    }

    public function down(): void
    {
        Schema::table('local_businesses', function (Blueprint $table) {
            $table->dropIndex(['lead_status']);
            $table->dropIndex(['next_action_at']);
            $table->dropColumn([
                'lead_status', 'confidence_score', 'last_contacted_at', 'next_action_at',
                'contact_channel', 'evidence_notes', 'menu_rights_status',
            ]);
        });
    }
};
