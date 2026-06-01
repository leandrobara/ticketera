<?php

namespace App\Http\Requests;


class DeleteTemplateCategoryRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $templateCategory = request()->templateCategory;
            $clientId = request()->input('client')->id;

            if ($templateCategory->client_id != $clientId) {
                $validator->errors()->add(
                    'client_id', 'template_category_client_does_not_match_with_authenticated_client'
                );
                return false;
            }

            if ($templateCategory->emailTemplateCount) {
                $validator->errors()->add(
                    'delete_template_category',
                    'template_category_has_associated_email_template'
                );
                return false;
            }

            if ($templateCategory->whatsAppTemplateCount) {
                $validator->errors()->add(
                    'delete_template_category',
                    'template_category_has_associated_whatsapp_template'
                );
                return false;
            }

            if ($templateCategory->taskTemplateCount) {
                $validator->errors()->add('delete_template_category', 'template_category_has_associated_task_template');
                return false;
            }
        });
    }

}
