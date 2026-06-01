<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAccessToken extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'expires_at',
        'token_hash',
        'last_used_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, AdminAccessToken>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
