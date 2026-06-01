<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InWAutomationSequenceReturnFields implements Rule
{
    private $allowedFields = [
        'id',
        'client',
        'client_id',
        'enabled',
        'name',
        'client',
        'do_not_send_weekends',
        'cancellingStatus',
        'triggeringStatus',
        'triggeringTags',
        'cancellingTags',
        'wAutomationSequenceSteps',
        'cancel_if_sequence_was_sent',
        'trigger_type',
        'triggering_status_ids',
        'triggering_tag_ids',
        'cancelling_status_ids',
        'cancelling_tag_ids',
        'cancel_if_sequence_was_sent',
        'do_not_send_weekends',
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
