<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PresentationTicketType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'price',
        'stock',
        'is_active',
        'sort_order',
        'presentation_id',
        'promotion_name',
        'promotion_type',
        'promotion_value',
        'promotion_bundle_quantity',
        'promotion_pay_quantity',
        'promotion_access_code',
        'promotion_is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:6',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'promotion_value' => 'decimal:6',
            'promotion_bundle_quantity' => 'integer',
            'promotion_pay_quantity' => 'integer',
            'promotion_is_active' => 'boolean',
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

}
