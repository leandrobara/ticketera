<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\InWhatsAppTemplateReturnFields;


class UpdateWhatsAppTemplateRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string', 'max:10000'],
            'is_proposal' => ['sometimes', 'boolean'],
            'whatsapp_attachment_id' => ['sometimes', 'nullable', 'integer', 'exists:WhatsAppAttachments,id'],
            'template_category_id' => ['sometimes', 'nullable', 'integer', 'exists:TemplateCategories,id'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InWhatsAppTemplateReturnFields()],
            'enable_meta_template' => ['sometimes', 'boolean'],
            'meta_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_header_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_footer_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_variables' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->whatsAppTemplate->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'client_id',
                    'WhatsApp Template client does not match with authenticated client'
                );
            }
        });
    }

    public function validatedAttributes()
    {
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        return $validated;
    }
}
