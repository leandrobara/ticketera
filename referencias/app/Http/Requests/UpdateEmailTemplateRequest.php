<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Attachment;
use App\Rules\InEmailTemplateReturnFields;


class UpdateEmailTemplateRequest extends APIBaseRequest
{

    protected $attachments = [];


    public function rules()
    {
        return [
            'body' => ['sometimes', 'string'],
            'title' => ['sometimes', 'string'],
            'subject' => ['sometimes', 'string'],
            'is_proposal' => ['sometimes', 'boolean'],
            'is_automation' => ['sometimes', 'boolean'],
            'attachment_ids' => ['sometimes', 'array'],
            'attachment_ids.*' => ['sometimes', 'integer'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InEmailTemplateReturnFields()],
            'template_category_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $this->attachments = collect([]);
                $client = request()->input('client');
                $attachmentIds = collect(request()->input('attachment_ids', []));
            
                if (request()->emailTemplate->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id',
                        'email_template_client_does_not_match_with_authenticated_client'
                    );
                }

                if ($attachmentIds->isNotEmpty()) {
                    $attachments = Attachment::where('client_id', $client->id)->whereIn('id', $attachmentIds)->get();
                    if ($attachments->count() != $attachmentIds->count()) {
                        $validator->errors()->add(
                            'attachment_ids', 'attachments_does_not_match_with_authenticated_client'
                        );
                        return false;
                    }
                    $this->attachments = $attachments;
                }
            });
        }
    }


    public function validatedAttributes()
    {
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        if (isset($validated['attachment_ids'])) {
            $validated['attachments'] = $this->attachments;
            unset($validated['attachment_ids']);
        }
        return $validated;
    }

}
