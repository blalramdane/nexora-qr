<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->foreignId('template_version_id')
                ->nullable()
                ->after('template_id')
                ->constrained('template_versions')
                ->nullOnDelete();
            $table->index(['template_id', 'template_version_id']);
        });

        DB::table('menus')
            ->whereNull('template_version_id')
            ->orderBy('id')
            ->eachById(function ($menu): void {
                $versionId = DB::table('template_versions')
                    ->where('template_id', $menu->template_id)
                    ->where('is_active', true)
                    ->orderByDesc('version')
                    ->value('id');

                if ($versionId !== null) {
                    DB::table('menus')
                        ->where('id', $menu->id)
                        ->update(['template_version_id' => $versionId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropForeign(['template_version_id']);
            $table->dropIndex(['template_id', 'template_version_id']);
            $table->dropColumn('template_version_id');
        });
    }
};
