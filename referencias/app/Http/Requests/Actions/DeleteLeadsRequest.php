<?php

namespace App\Http\Requests\Actions;

use App\Models\Lead;
use App\Rules\InLeadReturnFields;
use App\Http\Requests\APIBaseRequest;


class DeleteLeadsRequest extends APIBaseRequest
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

                $leadIds = request()->input('lead_id');
                $leads = Lead::where('client_id', $clientId)->withTrashed()->whereIn('id', $leadIds)->get();
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


    public function getLeads()
    {
        $validated = parent::validated();
        return $this->leads;
    }

    public function getLeadIds()
    {
        $validated = parent::validated();
        return $validated['lead_id'];
    }

}
