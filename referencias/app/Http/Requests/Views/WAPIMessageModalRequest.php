<?php

namespace App\Http\Requests\Views;

use App\Models\Lead;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class WAPIMessageModalRequest extends APIBaseRequest
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
                if (request()->user->wapi_is_paused) {
                    $validator->errors()->add('wapi', 'wapi_is_paused');
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
