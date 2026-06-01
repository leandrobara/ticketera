<?php

namespace App\Http\Requests\WhatsAppSenderExtension;

use Illuminate\Support\Str;
use App\Models\WhatsAppSending;
use App\Models\LeadContactPhone;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;
use App\DTO\WhatsAppSenderExtension\WhatsAppSenderCreateSendingDTO;
use App\Services\API\WhatsAppSendingService;


class CreateWhatsAppSendingRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'chatMessage' => ['required', 'string'],
            'phonesMap' => ['required', 'array'],
            'phonesMap.*.leadId' => ['required', 'integer'],
            'phonesMap.*.leadContactPhoneId' => ['required', 'integer'],
            'phonesMap.*.phoneNumber' => ['required', 'digits_between:8,15'],
            'phonesMap.*.variables' => ['sometimes', 'array', 'nullable'],
        ];
    }
    

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $user = request()->input('user');
                $client = request()->input('client');
                $clientSettings = $client->clientSettings;
                
                if (!$clientSettings->enable_whatsapp_sender_extension) {
                    $validator->errors()->add('whatsapp_sending', 'whatsapp_sender_plugin_is_not_enabled');
                    return false;
                }

                $currentSending = resolve(WhatsAppSendingService::class)->findCurrentSendingByUserAndType(
                    $user, WhatsAppSending::WAP_SENDER_TYPE
                );
                if ($currentSending) {
                    $validator->errors()->add('whatsapp_sending', 'current_whatsapp_sending_already_exists');
                    return false;
                }

                $phonesMapCollection = new Collection(request()->input('phonesMap'));
                $leadIds = $phonesMapCollection->pluck('leadId');
                $leadContactPhoneIds = $phonesMapCollection->pluck('leadContactPhoneId');
                $leadContactPhones = LeadContactPhone::whereIn('id', $leadContactPhoneIds)
                    ->whereIn('lead_id', $leadIds)
                    ->where('client_id', $client->id)
                    ->get()
                ;
                $phonesMapCount = $phonesMapCollection->count();
                $leadContactPhonesCount = $leadContactPhones->count();
                if ($phonesMapCount != $leadContactPhonesCount) {
                    $validator->errors()->add('whatsapp_sending', 'some_lead_contact_phones_do_not_exist');
                    return false;
                }
            }
        });
    }


    public function validatedDTO(): WhatsAppSenderCreateSendingDTO
    {
        $val = parent::validated();
        $dto = new WhatsAppSenderCreateSendingDTO(request()->user, $val['phonesMap'], $val['chatMessage']);
        return $dto;
    }

}
