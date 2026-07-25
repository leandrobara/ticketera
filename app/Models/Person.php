<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use SoftDeletes;

    protected $fillable = [
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

    public function showCredits(): HasMany
    {
        return $this->hasMany(ShowCredit::class);
    }
}
