<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InWAutomationSequenceStepReturnFields implements Rule
{

    private $allowedFields = [
        'id',
        'client',
        'client_id',
        'send_hour',
        'tagsToAdd',
        'statusToAdd',
        'send_delay_type',
        'send_delay_days',
        'send_delay_minutes',
        'sendWhatsAppTemplate',
        'wAutomationSequence',
        'whatsapp_template_id',
        'wautomation_sequence_id',
        'created_at',
        'updated_at',
        'deleted_at',
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
        return 'The field "' . $this->customErrVal . '" is not an WAutomationSequence field.';
    }
}
