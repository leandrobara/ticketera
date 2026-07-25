<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

class CreateNewsletterSubscriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'show_id' => ['nullable', 'integer', 'exists:shows,id'],
            'name' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
