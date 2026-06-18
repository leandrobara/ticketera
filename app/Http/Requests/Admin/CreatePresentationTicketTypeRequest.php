<?php

namespace App\Http\Requests\Admin;

use App\Models\Presentation;
use Illuminate\Foundation\Http\FormRequest;

class CreatePresentationTicketTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:160'],
            'price' => ['required', 'numeric', 'decimal:0,6', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'show_id' => ['required', 'integer', 'exists:shows,id'],
            'presentation_id' => ['required', 'integer', 'exists:presentations,id'],
        ];
    }

    public function withValidator($validator): void
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $presentation = Presentation::find($this->input('presentation_id'));

                if (!$presentation) {
                    return;
                }

                if ((int) $presentation->show_id !== (int) $this->input('show_id')) {
                    $validator->errors()->add(
                        'presentation_id',
                        'The selected presentation does not belong to the selected show.'
                    );
                }
            });
        }
    }
}
