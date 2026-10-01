<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ShowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Show extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ShowFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'title',
        'contact_email',
        'whatsapp_phone',
        'comments_enabled',
        'slug',
        'genre',
        'format',
        'subtitle',
        'age_rating',
        'synopsis',
        'additional_information',
        'production_note',
        'instagram_url',
        'facebook_url',
        'x_url',
        'tiktok_url',
        'youtube_url',
        'pinterest_url',
        'website_url',
        'faqs',
        'service_fee_type',
        'service_fee_fixed_amount',
        'service_fee_percentage',
        'service_fee_minimum_unit_amount',
    ];

    protected function casts(): array
    {
        return [
            'comments_enabled' => 'boolean',
            'faqs' => 'array',
            'service_fee_fixed_amount' => 'decimal:6',
            'service_fee_percentage' => 'decimal:6',
            'service_fee_minimum_unit_amount' => 'decimal:6',
        ];
    }

    public function credits(): HasMany
    {
        return $this->hasMany(ShowCredit::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ShowImage::class);
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    public function performanceHistories(): HasMany
    {
        return $this->hasMany(ShowPerformanceHistory::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ShowLink::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function mainImage(): HasOne
    {
        return $this->hasOne(ShowImage::class)->where('is_main', true);
    }
}
