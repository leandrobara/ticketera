<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

class ListPresentationsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'promo_code' => ['nullable', 'string', 'max:80'],
        ];
    }

    // metodo que se ejecuta antes que Laravel valide
    protected function prepareForValidation(): void
    {
        if (!$this->has('promo_code')) {
            return;
        }

        $promoCode = trim((string) $this->input('promo_code'));

        $this->merge([
            'promo_code' => $promoCode === '' ? null : mb_strtolower($promoCode),
        ]);
    }
}
