<?php

namespace App\Http\Requests\Views\WhatsAppMetaAPI;

use App\Models\Lead;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class WhatsAppMetaAPIMessageModalRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'lead_id' => ['required', 'array'],
            'lead_id.*' => ['required', 'integer'],
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

                if (!request()->user->whatsAppMetaAPIConnection) {
                    $validator->errors()->add('whatsapp_meta_api', 'whatsapp_meta_api_connection_does_not_exist');
                    return false;
                }
                
                $leadIds = request()->input('lead_id');
                $leads = Lead::where('client_id', request()->client->id)->whereIn('id', $leadIds)->get();
                $leadIdsCount = count($leadIds);
                $leadsCount = $leads->count();
                if ($leadIdsCount != $leadsCount) {
                    $validator->errors()->add('lead_id', 'some_leads_do_not_belong_to_this_client');
                    return false;
                }
            });
        }
    }


    public function validatedLeadIds(): Collection
    {
        $validated = parent::validated();
        return collect($validated['lead_id']);
    }

}
