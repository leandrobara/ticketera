<?php

namespace App\Http\Requests\WhatsAppSenderExtension;

use App\Models\whatsAppAttachment;
use App\Http\Requests\APIBaseRequest;


class DownloadAttachmentRequest extends APIBaseRequest
{

    public whatsAppAttachment $whatsAppAttachment;

    public function rules()
    {
        return [];
    }
    

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $wapSendingMsg = request()->wapSendingMsg;
                $attachmentHash = request()->attachmentHash;

                if (!$wapSendingMsg->whatsAppSending->whatsAppAttachment) {
                    $validator->errors()->add('whatsapp_sending', 'non_existent_attachment');
                    return false;
                }

                $hash = $wapSendingMsg->whatsAppSending->whatsAppAttachment->hash;
                if ($attachmentHash != $hash) {
                    $validator->errors()->add('whatsapp_attachment', 'hash_does_not_match');
                    return false;
                }

                $this->whatsAppAttachment = $wapSendingMsg->whatsAppSending->whatsAppAttachment;
            }
        });
    }

}
