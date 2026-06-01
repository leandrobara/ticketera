<?php

namespace App\Http\Requests;

use App\Models\LeadAttachment;


class DeleteLeadAttachmentRequest extends APIBaseRequest
{
    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                if (request()->leadAttachment->lead_id != request()->lead->id) {
                    $validator->errors()->add('client_id', 'lead_does_not_match_with_lead_attachment');
                    return false;
                }
    
                if (request()->leadAttachment->client_id != request()->input('client')->id) {
                    $validator->errors()->add('client_id', 'client_does_not_match_with_authenticated_client');
                    return false;
                }
            }
        });
    }
}
