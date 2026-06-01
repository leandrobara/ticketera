<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InLeadReturnFields implements Rule
{

    private $customErrVal;

    private $allowedFields = [
        'id',
        'leads_query_id',
        'user',
        'client',
        'landing',
        'tags',
        'status',
        'acquisitionChannel',
        'mainLeadContact',
        'leadContacts',
        'emailDraft',
        'method',
        'notes',
        'quality',
        'company',
        'country_code',
        'website',
        'other_fields',
        'serialized_fields',
        'message',
        'hash',
        'utm_source',
        'utm_medium',
        'utm_content',
        'utm_campaign',
        'utm_keywords',
        'lead_created_at',
        'is_bulk_created',
        'is_whatsapp_form',
        'is_facebook_form',
        'is_manually_created',
        'leadCustomFields',
        'leadCustomFieldsValues',
        'googleAPIUserContact',
        'googleAPIUserContacts',
        'notification_email_sent_at',
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
        return 'The field "' . $this->customErrVal . '" is not a Lead field.';
    }

}
