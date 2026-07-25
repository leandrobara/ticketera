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
        'season_id',
        'capacity',
        'starts_at',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'revenue_amount' => 'decimal:6',
            'starts_at' => 'datetime',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
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
