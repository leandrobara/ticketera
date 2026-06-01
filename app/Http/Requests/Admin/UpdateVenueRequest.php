<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVenueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'has_bar' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'has_parking' => ['nullable', 'boolean'],
            'is_accessible' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'google_maps_url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
