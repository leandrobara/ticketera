<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class OrganizationInvitation extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'encrypted_token'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return url('/admin/invitation/'.Crypt::decryptString($this->encrypted_token));
    }

    protected function casts(): array
    {
        return ['roles' => 'array', 'show_ids' => 'array', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
