<?php

namespace App\Http\Requests\Admin;


use App\Models\Season;
use App\Models\Presentation;
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
            'season_id' => ['nullable', 'integer', 'exists:seasons,id'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'sold_out', 'cancelled'])],
        ];
    }

    public function withValidator($validator): void
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $presentation = $this->route('presentation');
                $seasonId = $this->input('season_id', $presentation?->season_id);
                $startsAt = $this->input('starts_at', $presentation?->starts_at);

                $exists = Presentation::query()
                    ->where('season_id', $seasonId)
                    ->where('starts_at', $startsAt)
                    ->whereKeyNot($presentation?->id)
                    ->exists()
                ;

                if ($exists) {
                    $validator->errors()->add(
                        'starts_at',
                        'A presentation already exists for this season and start time.'
                    );
                }

                if (Season::find($seasonId)?->closed_at) {
                    $validator->errors()->add('season_id', 'season_is_closed');
                }

                if (
                    $presentation
                    && (int) $seasonId !== (int) $presentation->season_id
                    && $presentation->tickets()->exists()
                ) {
                    $validator->errors()->add(
                        'season_id',
                        'presentation_with_tickets_cannot_change_season'
                    );
                }
            });
        }
    }
}
