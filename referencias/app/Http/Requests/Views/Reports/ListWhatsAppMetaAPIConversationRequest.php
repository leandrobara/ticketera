<?php

namespace App\Http\Requests\Views\Reports;

use App\Http\Requests\APIBaseRequest;


class ListWhatsAppMetaAPIConversationRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'page' => ['sometimes', 'nullable', 'int'],
            'limit' => ['sometimes', 'nullable', 'int'],
            'user_id' => ['sometimes', 'nullable', 'array'],
            'user_id.*' => ['int'],
            'filters' => ['sometimes', 'nullable', 'array'],
            'filters.lead' => ['sometimes', 'nullable', 'array'],
            'filters.lead.status_id' => ['sometimes', 'nullable', 'array'],
            'filters.lead.status_id.*' => ['int'],
            'filters.lead.tag_id' => ['sometimes', 'nullable', 'array'],
            'filters.lead.tag_id.*' => ['int'],
            'filters.lead.id' => ['sometimes', 'nullable', 'int', 'min:1'],
            'filters.customerPhoneNumberSearch' => [
                'sometimes', 'nullable', 'string', 'min:4', 'max:20', 'regex:/^\d+$/'
            ],
            // Filtros para buscar una conversación puntual (usado por real-time
            // cuando llega un mensaje de una conversación no cargada en la lista)
            'customerPhoneNumber' => ['sometimes', 'nullable', 'string'],
            'metaConnectedPhoneNumberId' => ['sometimes', 'nullable', 'string'],
            'messagesPerConversation' => ['sometimes', 'nullable', 'int', 'min:1', 'max:10'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                if (!request()->client->clientSettings->enable_whatsapp_meta_api) {
                    $validator->errors()->add('whatsapp_meta_api', 'whatsapp_meta_api_is_not_enabled');
                    return false;
                }
            });
        }
    }

}
