<?php

namespace App\Http\Requests\Admin;

use App\Models\Presentation;
use App\Models\Season;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreatePresentationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'capacity' => ['required', 'integer', 'min:0'],
            'season_id' => ['required', 'integer', 'exists:seasons,id'],
            'status' => ['required', Rule::in(['draft', 'published', 'sold_out', 'cancelled'])],
        ];
    }

    public function withValidator($validator): void
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {

                $seasonId = $this->input('season_id');
                $starts_at = $this->input('starts_at');

                $exists = Presentation::query()
                    ->where('season_id', $seasonId)
                    ->where('starts_at', $starts_at)
                    ->exists()
                ;

                if ($exists) {
                    $validator->errors()->add(
                        'starts_at',
                        'A presentation already exists for this season and start time.'
                    );
                }

                $season = Season::find($seasonId);

                if ($season?->closed_at) {
                    $validator->errors()->add('season_id', 'season_is_closed');
                }
            });
        }
    }
}
