<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Buyer extends Model
{
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'dni',
        'name',
        'email',
        'phone',
        'last_name',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
