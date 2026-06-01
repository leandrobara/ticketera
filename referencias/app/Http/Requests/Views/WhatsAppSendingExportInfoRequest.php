<?php

namespace App\Http\Requests\Views;

use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;
use App\Models\WhatsAppSendingMessage;
use App\Services\API\WhatsAppSendingMessageService;


class WhatsAppSendingExportInfoRequest extends APIBaseRequest
{

    private $whatsAppSendingMessages = [];


    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $wapSending = request()->whatsAppSending;

                if ($wapSending->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id', 'whatsapp_sending_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }

                $wapSendingMessages = $wapSending->whatsAppSendingMessages()->get();
                $wapSendingMessageFromOtherClient = $wapSendingMessages
                    ->where('client_id', '!=', $client->id)->first()
                ;
                if ($wapSendingMessageFromOtherClient) {
                    $validator->errors()->add(
                        'client_id', 'some_whatsapp_sending_message_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }
            }
        });
    }


    public function validated($key = null, $default = null): array
    {
        $val = parent::validated();
        $val['with'] = [
            'lead' => function ($query) {
                $query->with(['mainLeadContact'])->withTrashed();
            },
        ];
        return $val;
    }

}
