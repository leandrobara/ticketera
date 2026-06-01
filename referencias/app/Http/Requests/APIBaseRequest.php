<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Exceptions\ValidationException;

class APIBaseRequest extends FormRequest
{
    /**
     * @override
     *
     * This replace the original "failedValidation"
     * and throw a generica Validation exception merging
     * the error message bag in one string separated with EOLs
     */
    protected function failedValidation($validator)
    {
        $message = strtolower(
            str_replace(
                [' ', '.'],
                ['_', ''],
                $validator->getMessageBag()->first()
                // implode(PHP_EOL, $validator->getMessageBag()->all())
            )
        );
        throw new ValidationException($message, 400);
    }
}
