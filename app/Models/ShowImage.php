<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShowImage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'path',
        'type',
        'show_id',
        'caption',
        'alt_text',
        'is_main',
        'sort_order',
    ];

    protected $appends = [
        'url',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->path) {
            return null;
        }

        $cloudfrontDomain = config('filesystems.cloudfront_domain');

        if ($cloudfrontDomain) {
            return 'https://'.rtrim($cloudfrontDomain, '/').'/'.ltrim($this->path, '/');
        }

        return null;
    }
}
