<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'note',
        'city',
        'address',
        'has_bar',
        'capacity',
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

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }
}
