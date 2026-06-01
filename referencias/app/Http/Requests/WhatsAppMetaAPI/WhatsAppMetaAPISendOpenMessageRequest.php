<?php

namespace App\Http\Requests\WhatsAppMetaAPI;

use App\Helpers\PhonesHelper;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\LeadContactPhoneService;
use App\Models\MongoDB\WhatsAppMetaAPI\WhatsAppConversationMessage;
use App\DTO\WhatsAppMetaAPI\WhatsAppMetaAPINewSendingParametersDTO;
use App\Services\API\WhatsAppMetaAPI\WhatsAppConversationMessageService;


class WhatsAppMetaAPISendOpenMessageRequest extends APIBaseRequest
{

    protected $leadContactPhones = [];


    public function rules()
    {
        return [
            'isProposal' => 'bail|sometimes|boolean',
            'chatMessage' => 'required|string|max:1000',
            'leadContactPhoneIds' => ['required', 'array', 'size:1'],
            'leadContactPhoneIds.*' => ['required', 'integer'],
            'proposalInfo' => ['sometimes', 'array'],
            'proposalInfo.amount' => ['sometimes', 'numeric'],
            'proposalInfo.description' => ['sometimes', 'string', 'nullable'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $user = request()->user;
                $client = request()->client;
                $service = resolve(LeadContactPhoneService::class);
                $leadContactPhoneIds = collect(request()->input('leadContactPhoneIds'));

                if (!$client->clientSettings->enable_whatsapp_meta_api) {
                    $validator->errors()->add('whatsapp_meta_api', 'whatsapp_meta_api_is_not_enabled');
                    return false;
                }
                if (!$user->whatsAppMetaAPIConnection) {
                    $validator->errors()->add('whatsapp_meta_api', 'whatsapp_meta_api_connection_does_not_exist');
                    return false;
                }

                $with = [
                    'lead' => function ($q) {
                        $q->select(['id', 'company']);
                    },
                    'leadContact' => function ($q) {
                        $q->select(['id', 'name', 'last_name']);
                    },
                ];
                $opts = ['with' => $with];
                $this->leadContactPhones = $service->findByClientAndIds($client, $leadContactPhoneIds, $opts);
                if ($this->leadContactPhones->isEmpty()) {
                    $validator->errors()->add('leadContactPhoneIds', 'none_lead_contact_phone_exists');
                    return false;
                }

                // Validar ventana de 24h
                $leadContactPhone = $this->leadContactPhones->first();
                $phoneNumberId = $user->whatsAppMetaAPIConnection->phone_number_id;
                $formattedPhone = resolve(PhonesHelper::class)->getWhatsAppFormattedPhoneFromLeadContactPhone(
                    $leadContactPhone, $client
                );

                $conversationService = resolve(WhatsAppConversationMessageService::class);
                $recentMessages = $conversationService->listConversation(
                    $phoneNumberId, $formattedPhone, ['limit' => 10]
                );
                $lastIncomingMsg = $recentMessages->last(function ($msg) {
                    return $msg->direction === WhatsAppConversationMessage::DIRECTION_INCOMING;
                });

                $isWindowOpen = false;
                if ($lastIncomingMsg && $lastIncomingMsg->metaReceivedMessageTimestamp) {
                    $diffMinutes = now()->diffInMinutes($lastIncomingMsg->metaReceivedMessageTimestamp, true);
                    $isWindowOpen = $diffMinutes < (24 * 59); //le robo unos minutos por seguridad
                }

                if (!$isWindowOpen) {
                    $validator->errors()->add('conversationWindow', 'conversation_window_is_closed');
                    return false;
                }
            }
        });
    }


    public function dto(): WhatsAppMetaAPINewSendingParametersDTO
    {
        $val = parent::validated();
        $val['sendDate'] = null;
        $val['whatsAppAttachment'] = null;
        $val['isProposal'] = $val['isProposal'] ?? false;
        $val['leadContactPhones'] = $this->leadContactPhones;
        $dto = WhatsAppMetaAPINewSendingParametersDTO::buildFromRequestArray($val);
        return $dto;
    }

}
