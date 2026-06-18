<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'show_id',
        'order_id',
        'quantity',
        'unit_price',
        'subtotal_amount',
        'discount_amount',
        'total_amount',
        'presentation_ticket_type_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:6',
            'subtotal_amount' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'total_amount' => 'decimal:6',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function presentationTicketType(): BelongsTo
    {
        return $this->belongsTo(PresentationTicketType::class);
    }

    public function promotionSnapshot(): HasOne
    {
        return $this->hasOne(OrderItemPromotion::class);
    }
}
