<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Person extends Model
{
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'bio',
        'email',
        'phone',
        'last_name',
        'photo_path',
        'first_name',
        'website_url',
        'display_name',
        'document_type',
        'instagram_url',
        'document_number',
        'normalized_name',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        if (preg_match('~^https?://~i', $this->photo_path)) {
            return $this->photo_path;
        }

        if (str_starts_with($this->photo_path, '/')) {
            return $this->photo_path;
        }

        $cloudfrontDomain = config('filesystems.cloudfront_domain');

        return $cloudfrontDomain
            ? 'https://'.rtrim($cloudfrontDomain, '/').'/'.ltrim($this->photo_path, '/')
            : Storage::disk('s3')->url($this->photo_path);
    }

    public function showCredits(): HasMany
    {
        return $this->hasMany(ShowCredit::class);
    }
}
