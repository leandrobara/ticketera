<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InLeadCustomFieldReturnFields implements Rule
{

    private $customErrVal;
    private $allowedFields = [
        'id',
        'name',
        'order',
        'created_at',
        'updated_at',
        'deleted_at',
        'is_shown_in_leads_row',
        'leadCustomFieldValue',
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
        return 'The field "' . $this->customErrVal . '" is not a LeadCustomField field.';
    }

}
