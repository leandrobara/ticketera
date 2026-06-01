<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InUserReturnFields implements Rule
{

    private $customErrVal;
    private $allowedFields = [
        'id',
        'type',
        'name',
        'phone',
        'email',
        'client',
        'enabled',
        'username',
        'password',
        'last_name',
        'client_id',
        'email_sign',
        'updated_at',
        'remember_token',
        'wapi_is_synced',
        'email_from_name',
        'email_is_verified',
        'email_from_address',
        'email_sign_enabled',
        'enable_emails_reception',
        'enabled_to_receive_leads',
        'wapi_session_phone_number',
        'enable_new_lead_browser_alert',
        'automationsNewLeadWhereAssigned',
        'enable_alert_expiration_browser_alert',
        'enabled_export_leads_emails_reception',
        'enabled_delete_leads_emails_reception',
        'enable_alert_proposal_interaction_alert',
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
        return 'The field "' . $this->customErrVal . '" is not an User field.';
    }

}
