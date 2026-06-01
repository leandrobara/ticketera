<?php

namespace App\Http\Requests\Actions;

use App\Models\Lead;
use App\Rules\InLeadReturnFields;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class SetLeadsAcquisitionChannelRequest extends APIBaseRequest
{

    protected $leads = [];


    public function rules()
    {
        return [
            'lead_id' => ['required', 'array'],
            'lead_id.*' => ['required', 'integer'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $newAquisitionChannel = request()->newAquisitionChannel;
                $client = request()->input('client');
                $leadIds = request()->input('lead_id');

                if ($newAquisitionChannel->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id', 'new_acquisition_channel_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }

                $leads = Lead::where('client_id', $client->id)->whereIn('id', $leadIds)->get();
                if ($leads->count() != count($leadIds)) {
                    $validator->errors()->add('lead_id', 'some_leads_do_not_exist');
                    return false;
                }
                $this->leads = $leads;
            }
        });
    }


    public function getLeads(): Collection
    {
        $validated = parent::validated();
        unset($validated['lead_id']);
        return $this->leads;
    }

}
