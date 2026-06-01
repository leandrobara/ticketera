<?php

namespace App\Http\Requests;
use Illuminate\Http\UploadedFile;
use App\Services\API\LeadAttachmentService;


class StoreLeadAttachmentRequest extends APIBaseRequest
{
    
    public function rules()
    {
        return [
            'attachment' => [
                'required',
                'file',
                // 'mimes:jpeg,jpg,webp,webm,mp4,mp3,avi,csv,xlx,xlsx,ppt,pptx,bmp,png,pdf,docx,html,htm,txt',
            ],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $lead = request()->lead;
                $attachment = request()->attachment;

                if ($lead->client_id != request()->input('client')->id) {
                    $validator->errors()->add('client_id', 'client_does_not_match_with_authenticated_client');
                    return false;
                }

                $leadAttachment = resolve(LeadAttachmentService::class)->findOneByLeadAndFile($lead, $attachment);
                if ($leadAttachment) {
                    $validator->errors()->add('lead_attachment', 'lead_attachment_already_exists_for_this_lead');
                    return false;
                }
            
                $fileName = $attachment->getClientOriginalName();
                $leadAttachment = resolve(LeadAttachmentService::class)->findOneByLeadAndFileName($lead, $fileName);
                if ($leadAttachment) {
                    $validator->errors()->add('lead_attachment', 'lead_attachment_name_already_exists_for_this_lead');
                    return false;
                }
            }
        });
    }


    public function validated($key = null, $default = null)
    {
        $data = parent::all();
        return $data;
    }


    public function getUploadedFile(): UploadedFile
    {
        $validated = parent::validated();
        return $validated['attachment'];
    }
}
