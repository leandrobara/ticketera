<?php

namespace App\Http\Requests\Site;

use App\Http\Requests\Site\Concerns\ValidatesCommentToken;
use Illuminate\Foundation\Http\FormRequest;

class CreateCommentRequest extends FormRequest
{
    use ValidatesCommentToken;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'comment' => trim((string) $this->input('comment')),
        ]);
    }

    public function withValidator($validator): void
    {
        if (!$validator->failed()) {
            $validator->after(fn ($validator) => $this->validateCommentToken($validator));
        }
    }
}
