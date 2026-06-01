<?php

namespace App\Http\Requests;

use App\Models\Attachment;
use App\Services\API\AttachmentService;
use App\DTO\Attachments\SaveAttachmentDTO;


class DownloadAttachmentByHashRequest extends APIBaseRequest
{

    private $attachment;


    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->client;
                $hash = request()->attachmentHash;
                $attachment = resolve(AttachmentService::class)->findOneByClientAndHash($client, $hash);
                if (!$attachment) {
                    $validator->errors()->add('attachment', 'attachment_does_not_exists');
                    return false;
                }
                $this->attachment = $attachment;
            });
        }
    }


    public function getAttachment(): Attachment
    {
        return $this->attachment;
    }

}
