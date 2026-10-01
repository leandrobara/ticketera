<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'status',
        'show_id',
        'order_id',
        'order_item_id',
        'canceled_at',
        'checked_in_at',
        'presentation_id',
        'season_ticket_type_id',
    ];

    protected function casts(): array
    {
        return [
            'canceled_at' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class);
    }

    public function seasonTicketType(): BelongsTo
    {
        return $this->belongsTo(SeasonTicketType::class)->withTrashed();
    }
}
