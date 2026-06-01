<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class IsArrayOfStrings implements Rule
{

    /**
     * Determine if the validation rule passes.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!$value) {
            return false;
        }
        if (is_array($value)) {
            if (collect($value)->filter()->isEmpty()) {
                return false;
            }
        }
        if (!is_string($value)) {
            return false;
        }
        return true;
    }


    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Field :attribute must be an string or a non empty array.';
    }
}
