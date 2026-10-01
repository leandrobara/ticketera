<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Organization extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'image_path'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        $cloudfrontDomain = config('filesystems.cloudfront_domain');

        if ($cloudfrontDomain) {
            return 'https://'.rtrim($cloudfrontDomain, '/').'/'.ltrim($this->image_path, '/');
        }

        return Storage::disk('s3')->url($this->image_path);
    }

    protected static function booted(): void
    {
        static::updating(function ($organization) {
            if ($organization->isDirty('creator_user_id')) {
                throw ValidationException::withMessages(['creator_user_id' => 'El líder siempre es quien creó la organización.']);
            }
        });
    }

    public function memberships()
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function invitations()
    {
        return $this->hasMany(OrganizationInvitation::class);
    }
}
