<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'city',
        'address',
        'has_bar',
        'capacity',
        'description',
        'has_parking',
        'neighborhood',
        'is_accessible',
        'google_maps_url',
    ];

    protected function casts(): array
    {
        return [
            'has_bar' => 'boolean',
            'capacity' => 'integer',
            'has_parking' => 'boolean',
            'is_accessible' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Presentation>
     */
    public function presentations(): HasMany
    {
        return $this->hasMany(Presentation::class);
    }
}
