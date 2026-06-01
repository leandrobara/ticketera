<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InWhatsAppTemplateReturnFields implements Rule
{

    private $allowedFields = [
        'id',
        'body',
        'title',
        'client',
        'meta_id',
        'waba_id',
        'client_id',
        'meta_name',
        'attachment',
        'created_at',
        'updated_at',
        'is_proposal',
        'meta_category',
        'meta_header_text',
        'meta_footer_text',
        'templateCategory',
        'template_category_id',
        'whatsapp_attachment_id',
        'meta_body_variables_json',
        'meta_header_variables_json',
        'WAutomationsSequenceStep',
        'WAutomationsProposalResendRule',
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
        return 'The field "' . $this->customErrVal . '" is not a WhatsAppTemplate field.';
    }

}
