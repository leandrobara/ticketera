<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class InTaskReturnFields implements Rule
{
    private $allowedFields = [
        "id",
        "lead",
        "user",
        "client",
        "status",
        "title",
        "is_important",
        "user_id",
        "lead_id",
        "limit_date",
        "description",
        "created_at",
        "updated_at",
        "deleted_at",
    ];

    /**
     * @var string
     */
    private $customErrVal;


    /**
     * Determine if the validation rule passes.
     *
     * @param  string $attribute
     * @param  mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $ok = in_array($value, $this->allowedFields);
        if (!$ok) {
            $this->customErrVal = $value;
        }
        return $ok;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The field "' . $this->customErrVal . '" is not an Task field.';
    }
}
