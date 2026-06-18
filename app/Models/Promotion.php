<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'value',
        'ends_at',
        'starts_at',
        'is_active',
        'access_code',
        'pay_quantity',
        'bundle_quantity',
        'presentation_ticket_type_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:6',
            'ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'is_active' => 'boolean',
            'pay_quantity' => 'integer',
            'bundle_quantity' => 'integer',
        ];
    }

    public function presentationTicketType(): BelongsTo
    {
        return $this->belongsTo(PresentationTicketType::class);
    }

    public function orderItemPromotions(): HasMany
    {
        return $this->hasMany(OrderItemPromotion::class);
    }
}
