<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShowCreditRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'show_id' => ['nullable', 'integer', 'exists:shows,id'],
            'person_id' => ['nullable', 'integer', 'exists:people,id'],
            'section' => ['nullable', Rule::in(['cast', 'technical'])],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'character_name' => ['nullable', 'string', 'max:160'],
            'role_label' => ['sometimes', 'required', 'string', 'max:160'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg', 'mimetypes:image/jpeg', 'extensions:jpg,jpeg', 'max:500'],
            'photo_path_override' => ['prohibited'],
            'display_name_override' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function withValidator($validator): void
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                if (($this->has('person_id') || $this->has('display_name_override'))
                    && !filled($this->input('person_id'))
                    && !filled($this->input('display_name_override'))
                ) {
                    $validator->errors()->add(
                        'display_name_override',
                        'person_or_display_name_required'
                    );
                }
            });
        }
    }
}
