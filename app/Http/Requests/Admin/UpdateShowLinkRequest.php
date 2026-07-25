<?php

namespace App\Http\Requests\Admin;

class UpdateShowLinkRequest extends CreateShowLinkRequest
{
    public function rules(): array
    {
        return [
            'show_id' => ['sometimes', 'required', 'integer', 'exists:shows,id'],
            'text' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'url:http,https', 'max:2048'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
