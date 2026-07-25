<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Season extends Model
{
    use SoftDeletes;


    protected $fillable = [
        'show_id',
        'venue_id',
        'name',
        'status',
        'closed_season_id',
        'published_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_season_id' => 'integer',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function presentations(): HasMany
    {
        return $this->hasMany(Presentation::class);
    }

}
