<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presentation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'notes',
        'status',
        'show_id',
        'capacity',
        'venue_id',
        'starts_at',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'starts_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Show, Presentation>
     */
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /**
     * @return BelongsTo<Venue, Presentation>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return HasMany<PresentationTicketType>
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(PresentationTicketType::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
