<?php

namespace App\Http\Requests\Admin;

use App\Models\Presentation;
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
            'show_id' => ['required', 'integer', 'exists:shows,id'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'status' => ['required', Rule::in(['draft', 'published', 'sold_out', 'cancelled'])],
        ];
    }

    public function withValidator($validator): void
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {

                $show_id = $this->input('show_id');
                $venue_id = $this->input('venue_id');
                $starts_at = $this->input('starts_at');

                $exists = Presentation::query()
                    ->where('show_id', $show_id)
                    ->where('venue_id', $venue_id)
                    ->where('starts_at', $starts_at)
                    ->exists()
                ;

                if ($exists) {
                    $validator->errors()->add(
                        'starts_at',
                        'A presentation already exists for this show, venue and start time.'
                    );
                    return false;
                }
            });
        }
    }
}
