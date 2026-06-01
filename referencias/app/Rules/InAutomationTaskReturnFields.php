<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InAutomationTaskReturnFields implements Rule
{

    private $customErrVal;

    private $allowedFields = [
        'id',
        'enabled',
        'client_id',
        'updated_at',
        'create_hour',
        'trigger_type',
        'is_recurrent',
        'taskTemplate',
        'allowingTags',
        'tagsToAssign',
        'deleted_at_ts',
        'allowingStatus',
        'statusToAssign',
        'triggeringTags',
        'cancellingTags',
        'cancellingStatus',
        'triggeringStatus',
        'task_template_id',
        'create_delay_days',
        'allowing_tags_ids',
        'tags_ids_to_assign',
        'allowing_status_ids',
        'status_id_to_assign',
        'is_immediately_created',
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
        return "The field {$this->customErrVal} is not an AutomationTask field.";
    }

}
