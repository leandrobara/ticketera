<?php

namespace App\Http\Requests\Actions;

use App\Models\Lead;
use App\Rules\InLeadReturnFields;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class SyncMassiveWithGoogleContactsRequest extends APIBaseRequest
{

    public array $leadIds;


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
            $leadIds = request()->input('lead_id', []);
            $clientId = request()->input('client')->id;
            
            $leadsCount = count($leadIds);
            $existentLeadsCount = Lead::where('client_id', $clientId)->whereIn('id', $leadIds)->count();
            if ($existentLeadsCount != $leadsCount) {
                $validator->errors()->add('lead_id', 'Some leads do not exist');
                return false;
            }
            $this->leadIds = $leadIds;
        }
    }


    public function getLeadIds(): Collection
    {
        return new Collection($this->leadIds);
    }

}
