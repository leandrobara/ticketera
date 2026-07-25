<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShowCredit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'notes',
        'show_id',
        'section',
        'person_id',
        'sort_order',
        'role_label',
        'character_name',
        'photo_path_override',
        'display_name_override',
    ];

    protected $appends = [
        'photo_url',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo_path_override) {
            return null;
        }

        $cloudfrontDomain = config('filesystems.cloudfront_domain');

        if ($cloudfrontDomain) {
            return 'https://'.rtrim($cloudfrontDomain, '/').'/'.ltrim($this->photo_path_override, '/');
        }

        return str_starts_with($this->photo_path_override, '/')
            ? $this->photo_path_override
            : '/'.$this->photo_path_override;
    }
}
