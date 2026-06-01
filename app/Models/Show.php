<?php

namespace App\Models;

use Database\Factories\ShowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Show extends Model
{
    /** @use HasFactory<ShowFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'genre',
        'status',
        'format',
        'age_rating',
        'description',
        'published_at',
        'main_image_path',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }
}
