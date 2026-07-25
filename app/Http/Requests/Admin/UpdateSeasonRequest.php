<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeasonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'finished', 'cancelled'])],
        ];
    }

    public function withValidator($validator): void
    {
        if ($validator->failed()) {
            return;
        }

        $validator->after(function ($validator) {
            $season = $this->route('season');
            $newStatus = $this->input('status', $season?->status);

            if (!$season || $newStatus === $season->status) {
                return;
            }

            $allowedTransitions = [
                'draft' => ['published', 'cancelled'],
                'published' => ['finished', 'cancelled'],
                'finished' => [],
                'cancelled' => [],
            ];

            if (!in_array($newStatus, $allowedTransitions[$season->status] ?? [], true)) {
                $validator->errors()->add('status', 'invalid_season_status_transition');
            }
        });
    }
}
