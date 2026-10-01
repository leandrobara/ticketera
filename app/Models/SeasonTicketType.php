<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeasonTicketType extends Model
{
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'price',
        'stock',
        'is_active',
        'sort_order',
        'season_id',
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
     * @return BelongsTo<Season, SeasonTicketType>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
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
