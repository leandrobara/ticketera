<?php

namespace App\Http\Resources\Site;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'faqs' => $this->resource['faqs'],
            'slug' => $this->resource['slug'],
            'venue' => $this->resource['venue'],
            'title' => $this->resource['title'],
            'links' => $this->resource['links'],
            'genre' => $this->resource['genre'],
            'format' => $this->resource['format'],
            'credits' => $this->resource['credits'],
            'subtitle' => $this->resource['subtitle'],
            'synopsis' => $this->resource['synopsis'],
            'age_rating' => $this->resource['age_rating'],
            'social_links' => $this->resource['social_links'],
            'main_image_url' => $this->resource['main_image_url'],
            'production_note' => $this->resource['production_note'],
            'service_fee_type' => $this->resource['service_fee_type'],
            'duration_minutes' => $this->resource['duration_minutes'],
            'performance_history' => $this->resource['performance_history'],
            'service_fee_percentage' => $this->resource['service_fee_percentage'],
            'additional_information' => $this->resource['additional_information'],
            'service_fee_fixed_amount' => $this->resource['service_fee_fixed_amount'],
            'service_fee_minimum_unit_amount' => $this->resource['service_fee_minimum_unit_amount'],
        ];
    }
}
