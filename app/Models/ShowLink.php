<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShowLink extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'show_id',
        'text',
        'url',
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
