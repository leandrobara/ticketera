<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class IsArrayOfIntegers implements Rule
{

    private $canBeEmpty = false;


    public function __construct(array $opts = [])
    {
        if ($opts['canBeEmpty'] ?? false) {
            $this->canBeEmpty = true;
        }
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
        $collection = collect($value);

        if (!is_array($value)) {
            return false;
        }
        if (!$value && !$this->canBeEmpty) {
            return false;
        }

        if (is_array($value)) {
            if ($collection->filter()->isEmpty() && !$this->canBeEmpty) {
                return false;
            }
        }

        $notNumbers = $collection->filter(function ($value) {
            return !is_numeric($value);
        });
        if ($notNumbers->isNotEmpty()) {
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
        return 'Field :attribute must be an array of integers';
    }
}
