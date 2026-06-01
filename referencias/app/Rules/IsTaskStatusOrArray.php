<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class IsTaskStatusOrArray implements Rule
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
        $statuses = ['pending', 'completed', 'expired', 'non_expired'];

        if (!$value) {
            return false;
        }

        if (!is_string($value) && !is_array($value)) {
            return false;
        }

        if (is_string($value) && !in_array($value, $statuses)) {
            return false;
        }

        if (is_array($value)) {
            if (collect($value)->filter()->isEmpty()) {
                return false;
            }

            foreach ($value as $val) {
                if (!in_array($val, $statuses)) {
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
        return 'Field :attribute must be an string or a non empty array.';
    }
}
