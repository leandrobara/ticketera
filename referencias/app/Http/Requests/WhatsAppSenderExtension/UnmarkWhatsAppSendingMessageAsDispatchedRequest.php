<?php

namespace App\Http\Requests\WhatsAppSenderExtension;

use App\Models\WhatsAppSending;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\WhatsAppSendingService;


class UnmarkWhatsAppSendingMessageAsDispatchedRequest extends APIBaseRequest
{

    public $currentSending;


    public function rules()
    {
        return [];
    }

    
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $user = request()->input('user');
                $client = request()->input('client');
                $clientSettings = $client->clientSettings;
                
                $errorBag = $validator->errors();
                $currentSending = resolve(WhatsAppSendingService::class)->findCurrentSendingByUserAndType(
                    $user, WhatsAppSending::WAP_SENDER_TYPE
                );

                if (!$currentSending) {
                    $errorBag->add('whatsapp_sending', 'current_user_whatsapp_sending_does_not_exist');
                    return false;
                }
                if ($currentSending->client_id != $client->id) {
                    $errorBag->add('whatsapp_sending', 'whatsapp_sending_does_not_belong_to_client');
                    return false;
                }
                if ($currentSending->finished_date) {
                    $errorBag->add('whatsapp_sending', 'whatsapp_sending_was_already_finished');
                    return false;
                }
                if ($currentSending->cancelled_date) {
                    $errorBag->add('whatsapp_sending', 'whatsapp_sending_was_already_cancelled');
                    return false;
                }

                $wapMsg = request()->whatsAppSendingMessage;
                if ($wapMsg->client_id != $client->id) {
                    $errorBag->add('whatsapp_sending_message', 'whatsapp_sending_message_does_not_belong_to_client');
                    return false;
                }
                if (!$wapMsg->dispatched_date) {
                    $errorBag->add('whatsapp_sending_message', 'whatsapp_sending_message_was_not_dispatched');
                    return false;
                }
                if ($wapMsg->sent_date) {
                    $errorBag->add('whatsapp_sending_message', 'whatsapp_sending_message_was_already_sent');
                    return false;
                }
                if ($wapMsg->cancelled_date) {
                    $errorBag->add('whatsapp_sending_message', 'whatsapp_sending_message_was_already_cancelled');
                    return false;
                }

                $this->currentSending = $currentSending;
            }
        });
    }


    public function getCurrentSending(): WhatsAppSending
    {
        return $this->currentSending;
    }

}
