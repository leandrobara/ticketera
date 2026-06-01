<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InWAutomationAfterSendReturnFields implements Rule
{

    private $customErrVal;
    private $allowedFields = [
        'id',
        'lead',
        'client',
        'enabled',
        'client_id',
        'tagsToAdd',
        'tagsToRemove',
        'add_tags_ids',
        'add_new_note',
        'new_note_text',
        'deleted_at_ts',
        'statusToAssign',
        'remove_tags_ids',
        'apply_only_once',
        'assign_status_id',
        'only_apply_to_massive_sendings',
        'created_at',
        'updated_at',
        'deleted_at',
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
        return 'The field "' . $this->customErrVal . '" is not an WAutomationAfterSend field';
    }

}
