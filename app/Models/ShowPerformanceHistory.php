<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShowPerformanceHistory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'show_id',
        'year',
        'venue_name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
}
