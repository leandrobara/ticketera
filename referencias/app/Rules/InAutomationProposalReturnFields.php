<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class InAutomationProposalReturnFields implements Rule
{
    private $allowedFields = [
        'client',
        'interactionRule',
        'resendRule',
        'modifyLeadAfterSendRule'
    ];

    private $customErrVal;

    public function passes($attribute, $value)
    {
        $ok = in_array($value, $this->allowedFields);
        if (!$ok) {
            $this->customErrVal = $value;
        }
        return $ok;
    }

    public function message()
    {
        return 'The field "' . $this->customErrVal . '" is not an AutomationProposal field.';
    }
}
