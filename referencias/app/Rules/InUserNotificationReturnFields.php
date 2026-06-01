<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InUserNotificationReturnFields implements Rule
{

    private $allowedFields = [
        'id',
        'user',
        'client',
        'comments',
        'sent_date',
        'schedule_date',
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
        return 'The field "' . $this->customErrVal . '" is not an User Error Notification field.';
    }

}
