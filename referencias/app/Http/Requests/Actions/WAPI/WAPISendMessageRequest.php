<?php

namespace App\Http\Requests\Actions\WAPI;

use DateTime;
use Illuminate\Support\Collection;
use App\Models\WhatsAppAttachment;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\LeadContactPhoneService;
use App\DTO\WAPI\WAPINewSendingParametersDTO;


class WAPISendMessageRequest extends APIBaseRequest
{

    protected $attachment = null;
    protected $leadContactPhones = [];


    public function rules()
    {
        return [
            'chatMessage' => 'bail|required|string',
            'isProposal' => 'bail|sometimes|boolean',
            'attachmentId' => 'bail|nullable|sometimes|integer',
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
                if (!request()->client->clientSettings->enable_wapi) {
                    $validator->errors()->add('wapi', 'wapi_is_not_enabled');
                    return false;
                }
                if (!request()->user->wapi_session_phone_number) {
                    $validator->errors()->add('wapi', 'user_is_not_synced_with_wapi');
                    return false;
                }
                if (!request()->user->wapi_is_synced) {
                    $validator->errors()->add('wapi', 'user_is_not_synced_with_wapi');
                    return false;
                }
                $client = request()->client;
                $service = resolve(LeadContactPhoneService::class);
                $leadContactPhoneIds = collect(request()->input('leadContactPhoneIds'));

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

                $attachmentId = request()->input('attachmentId', null);
                if ($attachmentId) {
                    $attachment = WhatsAppAttachment::where('client_id', $client->id)
                        ->where('id', $attachmentId)
                        ->first()
                    ;
                    if (!$attachment) {
                        $validator->errors()->add('attachmentId', 'attachment_does_not_belong_to_client');
                        return false;
                    }
                    $this->attachment = $attachment;
                }
            }
        });
    }


    
    public function dto(): WAPINewSendingParametersDTO
    {
        $val = parent::validated();
        $val['sendDate'] = null;
        $val['attachment'] = $this->attachment;
        $val['leadContactPhones'] = $this->leadContactPhones;
        $dto = WAPINewSendingParametersDTO::buildFromRequestArray($val);
        return $dto;
    }

}
