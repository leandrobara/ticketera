<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class StringOrNumeric implements Rule
{

    private $nullable = true;


    public function __construct(array $opts = [])
    {
        $this->nullable = $opts['nullable'] ?? true;
    }


    /**
     * Determine if the validation rule passes.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return is_numeric($value) || is_string($value);
    }


    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Field :attribute must be string or numeric';
    }

}
