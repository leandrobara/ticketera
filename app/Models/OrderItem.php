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
        'paid_quantity',
        'unit_price',
        'unit_service_fee',
        'service_fee_type',
        'service_fee_fixed_amount',
        'service_fee_percentage',
        'service_fee_base_amount',
        'service_fee_minimum_applied',
        'service_fee_minimum_unit_amount',
        'subtotal_amount',
        'discount_amount',
        'service_fee_total_amount',
        'total_amount',
        'presentation_ticket_type_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'paid_quantity' => 'integer',
            'unit_price' => 'decimal:6',
            'unit_service_fee' => 'decimal:6',
            'service_fee_fixed_amount' => 'decimal:6',
            'service_fee_percentage' => 'decimal:6',
            'service_fee_base_amount' => 'decimal:6',
            'service_fee_minimum_applied' => 'boolean',
            'service_fee_minimum_unit_amount' => 'decimal:6',
            'subtotal_amount' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'service_fee_total_amount' => 'decimal:6',
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
