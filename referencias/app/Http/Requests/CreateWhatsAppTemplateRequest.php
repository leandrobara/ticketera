<?php

namespace App\Http\Requests;

use App\Rules\InWhatsAppTemplateReturnFields;

class CreateWhatsAppTemplateRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'is_proposal' => ['required', 'boolean'],
            'template_category_id' => ['nullable', 'integer', 'exists:TemplateCategories,id'],
            'whatsapp_attachment_id' => ['nullable', 'integer', 'exists:WhatsAppAttachments,id'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InWhatsAppTemplateReturnFields()],
            'enable_meta_template' => ['required', 'boolean'],
            'meta_category' => ['nullable', 'string', 'max:255'],
            'meta_header_text' => ['nullable', 'string', 'max:255'],
            'meta_footer_text' => ['nullable', 'string', 'max:255'],
            'meta_variables' => ['nullable', 'array'],
        ];
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
