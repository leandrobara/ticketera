<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class IsRequiredIntegerOrArray implements Rule
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

        $isArray = is_array($value);
        $isInt = !$isArray && intval($value);

        if (!$isArray && !$isInt) {
            return false;
        }

        if ($isArray) {
            foreach ($value as $val) {
                if (!is_int(filter_var($val, FILTER_VALIDATE_INT))) {
                    return false;
                }
            }
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
        return 'Field :attribute must be an integer or an array of integers.';
    }
}
