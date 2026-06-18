<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemPromotion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'order_item_id',
        'promotion_id',
        'promotion_name',
        'promotion_type',
        'promotion_value',
        'promotion_access_code',
        'bundle_quantity',
        'pay_quantity',
        'discount_amount',
    ];

    protected function casts(): array
    {
        return [
            'promotion_value' => 'decimal:6',
            'bundle_quantity' => 'integer',
            'pay_quantity' => 'integer',
            'discount_amount' => 'decimal:6',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
