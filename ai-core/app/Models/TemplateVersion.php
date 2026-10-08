<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class TemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = ['template_id', 'version', 'schema', 'is_active'];

    protected function casts(): array
    {
        return ['schema' => 'array', 'is_active' => 'boolean'];
    }

    public function activate(): self
    {
        return DB::transaction(function (): self {
            static::query()
                ->where('template_id', $this->template_id)
                ->where('id', '<>', $this->id)
                ->update(['is_active' => false]);

            $this->forceFill(['is_active' => true])->save();

            return $this->refresh();
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
