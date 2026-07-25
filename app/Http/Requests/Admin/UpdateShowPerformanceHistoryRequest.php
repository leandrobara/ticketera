<?php

namespace App\Http\Requests\Admin;

class UpdateShowPerformanceHistoryRequest extends CreateShowPerformanceHistoryRequest
{
    public function rules(): array
    {
        return [
            'show_id' => ['sometimes', 'required', 'integer', 'exists:shows,id'],
            'year' => ['sometimes', 'required', 'string', 'max:255'],
            'venue_name' => ['sometimes', 'required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
