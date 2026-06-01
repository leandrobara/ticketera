<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class IsRequiredNullableIntegerOrArray implements Rule
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
        $isArray = is_array($value);
        $isInt = !$isArray && intval($value);
        $isNull = !$isInt && $value === null || is_string($value) && strtolower($value) === 'null';

        if (!$isArray && !$isInt && !$isNull) {
            return false;
        }

        if ($isArray) {
            foreach ($value as $val) {
                if (
                    !is_int(filter_var($val, FILTER_VALIDATE_INT))
                    && strtolower($val) !== 'null'
                    && $val !== null
                ) {
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
        return 'Field :attribute must be a nullable integer or an array of nullable integers.';
    }
}
