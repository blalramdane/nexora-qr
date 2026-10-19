<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuScanJob extends Model
{
    use BelongsToRestaurant, HasFactory;

    protected $fillable = ['restaurant_id', 'menu_id', 'user_id', 'status', 'image_paths', 'raw_ocr_text', 'extracted_data', 'reviewed_data', 'import_summary', 'error_message', 'processed_at', 'reviewed_at', 'imported_at'];

    protected function casts(): array
    {
        return [
            'image_paths' => 'array',
            'extracted_data' => 'array',
            'reviewed_data' => 'array',
            'import_summary' => 'array',
            'processed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
