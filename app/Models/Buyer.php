<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Buyer extends Model
{
    use SoftDeletes;

    protected $fillable = [
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
}
