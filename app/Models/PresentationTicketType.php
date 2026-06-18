<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PresentationTicketType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'price',
        'stock',
        'show_id',
        'is_active',
        'sort_order',
        'presentation_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:6',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Presentation, PresentationTicketType>
     */
    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function promotion(): HasOne
    {
        return $this->hasOne(Promotion::class);
    }
}
