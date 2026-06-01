<?php

namespace App\Http\Requests\Actions;

use App\Models\Lead;
use App\Rules\InLeadReturnFields;
use App\Http\Requests\APIBaseRequest;


class SetLeadsStatusRequest extends APIBaseRequest
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
                $clientId = request()->input('client')->id;
                $correctClient = request()->newStatus->client_id === $clientId;
                if (!$correctClient) {
                    $validator->errors()->add(
                        'client_id', 'New status client does not match with authenticated client'
                    );
                    return false;
                }

                $leadIds = request()->input('lead_id');
                $leads = Lead::where('client_id', $clientId)->whereIn('id', $leadIds)->with(['status'])->get();
                $leadIdsCount = count($leadIds);
                $leadsCount = $leads->count();
                if ($leadIdsCount != $leadsCount) {
                    $validator->errors()->add('lead_id', 'Some leads do not exist');
                    return false;
                }

                $this->leads = $leads;
            }
        });
    }


    public function validatedAttributes()
    {
        $validated = parent::validated();
        unset($validated['lead_id']);
        $validated['leads'] = $this->leads;
        return $validated;
    }

}
