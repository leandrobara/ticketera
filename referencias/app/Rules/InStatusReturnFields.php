<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class InStatusReturnFields implements Rule
{
    private $allowedFields = [
        'id',
        'client',
        'client_id',
        'name',
        'statusCategory',
        'hash',
        'text_color',
        'background_color',
        'sale_probability',
        'order',
        'created_at',
        'updated_at',
        'deleted_at',
        'status_category_id',
        'wAutomationsSequence',
        'automationsEmailSend',
        'automationsProposalInteractionRule',
        'automationsProposalModifyLeadAfterSendRule',
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
        return 'The field "' . $this->customErrVal . '" is not an Status field.';
    }
}
