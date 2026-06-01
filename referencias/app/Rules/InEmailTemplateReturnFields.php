<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class InEmailTemplateReturnFields implements Rule
{
    private $allowedFields = [
        'id',
        'user',
        'body',
        'name',
        'title',
        'client',
        'user_id',
        'subject',
        'client_id',
        'updated_at',
        'created_at',
        'is_proposal',
        'attachments',
        'is_automation',
        'templateCategory',
        'automationsNewLead',
        'template_category_id',
        'automationsEmailSendStep',
        'automationsProposalResendRule',
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
        return 'The field "' . $this->customErrVal . '" is not a EmailTemplate field.';
    }
}
