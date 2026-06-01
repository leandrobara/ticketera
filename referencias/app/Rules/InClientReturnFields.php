<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InClientReturnFields implements Rule
{

    private $customErrVal;
    private $allowedFields = [
        'id',
        'name',
        'emails',
        'version',
        'enabled',
        'timezone',
        'subdomain',
        'manager_id',
        'country_code',
        'google_ads_id',
        'leads_client_id',
        'email_from_name',
        'client_settings_id',
        'enabled_to_receive_leads',
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
        return 'The field "' . $this->customErrVal . '" is not an Client field.';
    }

}
