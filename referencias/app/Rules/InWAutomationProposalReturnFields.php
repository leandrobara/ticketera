<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InWAutomationProposalReturnFields implements Rule
{

    private $customErrVal;

    private $allowedFields = [
        'client',
        'resendRule',
        'modifyLeadAfterSendRule'
    ];


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
        return 'The field "' . $this->customErrVal . '" is not an WAutomationProposal field.';
    }

}
