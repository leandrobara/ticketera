<?php

namespace App\Http\Requests\Site;

use App\Http\Requests\Site\Concerns\ValidatesCommentToken;
use Illuminate\Foundation\Http\FormRequest;

class ValidateCommentTokenRequest extends FormRequest
{
    use ValidatesCommentToken;

    public function rules(): array
    {
        return [];
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => $this->validateCommentToken($validator));
    }
}
