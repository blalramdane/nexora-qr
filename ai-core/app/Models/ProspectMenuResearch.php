<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectMenuResearch extends Model
{
    protected $table = 'prospect_menu_researches';

    protected $fillable = [
        'local_business_id', 'created_by', 'status', 'source_website_url', 'discovered_links',
        'selected_menu_url', 'source_content_type', 'source_hash', 'extracted_data',
        'reviewed_data', 'error_message', 'discovered_at', 'processed_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'discovered_links' => 'array',
            'extracted_data' => 'array',
            'reviewed_data' => 'array',
            'discovered_at' => 'datetime',
            'processed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(LocalBusiness::class, 'local_business_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
