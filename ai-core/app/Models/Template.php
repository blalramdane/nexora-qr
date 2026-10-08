<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Template extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'name', 'description', 'category', 'is_premium', 'is_active'];

    protected function casts(): array
    {
        return ['is_premium' => 'boolean', 'is_active' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }

    public function createVersion(array $schema, bool $activate = false): TemplateVersion
    {
        return DB::transaction(function () use ($schema, $activate): TemplateVersion {
            $nextVersion = ((int) $this->versions()->max('version')) + 1;

            $version = $this->versions()->create([
                'version' => $nextVersion,
                'schema' => $schema,
                'is_active' => false,
            ]);

            if ($activate) {
                $version->activate();
            }

            return $version->fresh();
        });
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(TemplateVersion::class)
            ->latestOfMany('version')
            ->where('is_active', true);
    }
}
