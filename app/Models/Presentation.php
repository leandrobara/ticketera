<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Presentation extends Model
{
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'notes',
        'status',
        'season_id',
        'capacity',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'revenue_amount' => 'decimal:6',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return HasMany<SeasonTicketType>
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(SeasonTicketType::class, 'season_id', 'season_id');
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
