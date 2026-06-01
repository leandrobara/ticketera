<?php

namespace App\Http\Requests\Actions;

use App\Models\Lead;
use App\Rules\InLeadReturnFields;
use App\Http\Requests\APIBaseRequest;


class CreateLeadsMassiveNoteRequest extends APIBaseRequest
{
    protected $leads = [];


    public function rules()
    {
        return [
            'lead_id' => ['required', 'array'],
            'lead_id.*' => ['required', 'integer'],
            'text' => ['required', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $leadIds = request()->input('lead_id');
            $clientId = request()->input('client')->id;

            $leads = Lead::where('client_id', $clientId)->whereIn('id', $leadIds)->get();
            $leadIdsCount = count($leadIds);
            $leadsCount = $leads->count();
            if ($leadIdsCount != $leadsCount) {
                $validator->errors()->add('lead_id', 'Some leads do not exist');
                return false;
            }

            $this->leads = $leads;
        });
    }


    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        unset($validated['lead_id']);
        $validated['leads'] = $this->leads;
        return $validated;
    }

}
