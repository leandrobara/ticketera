<?php

namespace App\Http\Requests\Views\WAPSender;

use App\Models\Lead;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class WAPSenderMessageModalRequest extends APIBaseRequest
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
                if (!request()->client->clientSettings->enable_whatsapp_sender_job_sending) {
                    $validator->errors()->add('wap_sender', 'client_wap_sender_is_not_enabled');
                    return false;
                }
                if (!request()->user->wap_sender_session_phone_number) {
                    $validator->errors()->add('wap_sender', 'user_is_not_synced_with_wap_sender');
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
