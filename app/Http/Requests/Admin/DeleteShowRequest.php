<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DeleteShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $show = $this->route('show');

            if ($show?->seasons()->withTrashed()->exists()) {
                $validator->errors()->add(
                    'show',
                    'show_with_seasons_cannot_be_deleted'
                );
            }
        });
    }
}
