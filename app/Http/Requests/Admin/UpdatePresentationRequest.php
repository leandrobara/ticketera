<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePresentationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'show_id' => ['nullable', 'integer', 'exists:shows,id'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'sold_out', 'cancelled'])],
        ];
    }

    public function withValidator($validator): void
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $presentation = $this->route('presentation');
                $showId = $this->input('show_id', $presentation?->show_id);
                $venueId = $this->input('venue_id', $presentation?->venue_id);
                $startsAt = $this->input('starts_at', $presentation?->starts_at);

                $exists = \App\Models\Presentation::query()
                    ->where('show_id', $showId)
                    ->where('venue_id', $venueId)
                    ->where('starts_at', $startsAt)
                    ->whereKeyNot($presentation?->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'starts_at',
                        'A presentation already exists for this show, venue and start time.'
                    );
                }
            });
        }
    }
}
