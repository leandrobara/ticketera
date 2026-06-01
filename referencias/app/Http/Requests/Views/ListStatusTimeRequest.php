<?php

namespace App\Http\Requests\Views;

use App\Models\Lead;
use App\Rules\IsArrayOfIntegers;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class ListStatusTimeRequest extends APIBaseRequest
{

    private Collection $leadIds;


    public function rules()
    {
        return [
            'lead_ids' => ['required', new IsArrayOfIntegers()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $leadIds = request()->input('lead_ids');
                if ($leadIds) {
                    $leadsCount = Lead::where('client_id', $client->id)->whereIn('id', $leadIds)->count();
                    if ($leadsCount != count($leadIds)) {
                        $validator->errors()->add('lead_ids', 'not_all_leads_exists');
                        return false;
                    }
                    $this->leadIds = (new Collection($leadIds))->map(fn ($leadId) => intval($leadId));
                }
            }
        });
    }


    public function getLeadIds(): Collection
    {
        return $this->leadIds;
    }

}
