<?php

namespace App\Http\Requests\Actions\WAPSender;

use DateTime;
use DateTimeZone;
use App\Models\WhatsAppAttachment;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\LeadContactPhoneService;
use App\DTO\WAPSender\WAPSenderNewSendingParametersDTO;


class WAPSenderScheduleMessageRequest extends APIBaseRequest
{

    protected $attachment = null;
    protected $leadContactPhones = [];


    public function rules()
    {
        return [
            'chatMessage' => 'bail|required|string',
            'isProposal' => 'bail|sometimes|boolean',
            'sendDate' => 'required|date_format:Y-m-d\TH:i:sP',
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
                if (!request()->client->clientSettings->enable_whatsapp_sender_job_sending) {
                    $validator->errors()->add('wapi', 'wap_sender_is_not_enabled_for_client');
                    return false;
                }
                if (!request()->user->wap_sender_session_phone_number) {
                    $validator->errors()->add('wapi', 'wap_sender_is_not_enabled_for_user');
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

                $client = request()->client;
                $service = resolve(LeadContactPhoneService::class);
                $leadContactPhoneIds = collect(request()->input('leadContactPhoneIds'));
                $this->leadContactPhones = $service->findByClientAndIds($client, $leadContactPhoneIds, $opts);
                if ($this->leadContactPhones->isEmpty()) {
                    $validator->errors()->add('lead_contact_phone_id', 'none_lead_contact_phone_exists');
                    return false;
                }

                if ($this->leadContactPhones->count() != $leadContactPhoneIds->count()) {
                    $validator->errors()->add('lead_contact_phone_id', 'some_lead_contact_phone_does_not_exist');
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


    
    public function dto(): WAPSenderNewSendingParametersDTO
    {
        $val = parent::validated();
        $val['attachment'] = $this->attachment;
        $val['leadContactPhones'] = $this->leadContactPhones;

        $date = (new DateTime($val['sendDate']))->setTimezone(new DateTimeZone('UTC'));
        $val['sendDate'] = $date;
        
        $dto = WAPSenderNewSendingParametersDTO::buildFromRequestArray($val);
        return $dto;
    }

}
