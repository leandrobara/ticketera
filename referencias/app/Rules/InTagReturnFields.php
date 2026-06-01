<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class InTagReturnFields implements Rule
{
    private $allowedFields = [
        'id',
        'name',
        'hash',
        'client',
        'client_id',
        'text_color',
        'tagCategory',
        'last_used_date',
        'tag_category_id',
        'background_color',
        'created_at',
        'updated_at',
        'deleted_at',
        'automationsTask',
        'automationsEmailSend',
        'wAutomationsSequence',
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
        return 'The field "' . $this->customErrVal . '" is not a Tag field.';
    }
}
