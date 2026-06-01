<?php

namespace App\Http\Requests\WhatsAppMetaAPI;

use DateTime;
use Illuminate\Support\Collection;
use App\Models\WhatsAppAttachment;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\LeadContactPhoneService;
use App\DTO\WhatsAppMetaAPI\WhatsAppMetaAPINewSendingParametersDTO;


class WhatsAppMetaAPISendMessageRequest extends APIBaseRequest
{

    protected $leadContactPhones = [];
    protected $whatsAppAttachment = null;


    public function rules()
    {
        return [
            'isProposal' => 'bail|sometimes|boolean',
            'whatsAppAttachmentId' => 'bail|nullable|sometimes|integer',
            'leadContactPhoneIds' => ['required', 'array'],
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
                $whatsAppTemplate = request()->whatsAppTemplate;
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
                if ($this->leadContactPhones->count() != $leadContactPhoneIds->count()) {
                    $validator->errors()->add('leadContactPhoneIds', 'some_lead_contact_phone_does_not_exist');
                    return false;
                }

                $whatsAppAttachmentId = request()->input('whatsAppAttachmentId', null);
                if ($whatsAppAttachmentId) {
                    $whatsAppAttachment = WhatsAppAttachment::where('client_id', $client->id)
                        ->where('id', $whatsAppAttachmentId)
                        ->first()
                    ;
                    if (!$whatsAppAttachment) {
                        $validator->errors()->add('whatsAppAttachmentId', 'attachment_does_not_belong_to_client');
                        return false;
                    }
                    
                    // Validar que la extensión coincida con la del attachment de la plantilla
                    $templateAttachment = $whatsAppTemplate->whatsAppAttachment;
                    if (!$templateAttachment) {
                        $validator->errors()->add('whatsAppAttachmentId', 'template_does_not_have_attachment');
                        return false;
                    }
                    if ($whatsAppAttachment->extension !== $templateAttachment->extension) {
                        $validator->errors()->add('whatsAppAttachmentId', 'attachment_extension_does_not_match');
                        return false;
                    }
                    
                    $this->whatsAppAttachment = $whatsAppAttachment;
                }
            }
        });
    }


    
    public function dto(): WhatsAppMetaAPINewSendingParametersDTO
    {
        $val = parent::validated();
        $val['sendDate'] = null;
        $val['leadContactPhones'] = $this->leadContactPhones;
        $val['whatsAppAttachment'] = $this->whatsAppAttachment;
        $dto = WhatsAppMetaAPINewSendingParametersDTO::buildFromRequestArray($val);
        return $dto;
    }

}
