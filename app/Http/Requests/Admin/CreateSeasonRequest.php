<?php

namespace App\Http\Requests\Admin;

use App\Models\Season;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSeasonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'show_id' => ['required', 'integer', 'exists:shows,id'],
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'name' => ['nullable', 'string', 'max:160'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ];
    }

    public function withValidator($validator): void
    {
        if ($validator->failed()) {
            return;
        }

        $validator->after(function ($validator) {
            $exists = Season::query()
                ->where('show_id', $this->integer('show_id'))
                ->where('venue_id', $this->integer('venue_id'))
                ->where('closed_season_id', 0)
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'venue_id',
                    'open_season_already_exists_for_show_and_venue'
                );
            }
        });
    }
}
