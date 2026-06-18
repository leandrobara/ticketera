<?php

namespace App\Http\Requests\Admin;

use App\Models\Presentation;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePresentationTicketTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'presentation_id' => ['nullable', 'integer', 'exists:presentations,id'],
            'name' => ['nullable', 'string', 'max:160'],
            'price' => ['nullable', 'numeric', 'decimal:0,6', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $ticketType = $this->route('presentationTicketType');
                $presentationId = $this->input('presentation_id', $ticketType?->presentation_id);
                $showId = $this->input('show_id', $ticketType?->show_id);
                $presentation = Presentation::find($presentationId);

                if (!$presentation || !$showId) {
                    return;
                }

                if ((int) $presentation->show_id !== (int) $showId) {
                    $validator->errors()->add(
                        'presentation_id',
                        'The selected presentation does not belong to the selected show.'
                    );
                }
            });
        }
    }
}
