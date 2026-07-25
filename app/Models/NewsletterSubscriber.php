<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsletterSubscriber extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'show_id',
        'name',
        'email',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
}
