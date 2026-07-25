<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FindPersonCandidatesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:160'],
            'document_type' => ['nullable', 'string', 'max:30', 'required_with:document_number'],
            'document_number' => ['nullable', 'string', 'max:80', 'required_with:document_type'],
        ];
    }
}
