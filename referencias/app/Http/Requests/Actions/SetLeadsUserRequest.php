<?php

namespace App\Http\Requests\Actions;

use App\Models\Lead;
use App\Rules\InLeadReturnFields;
use App\Http\Requests\APIBaseRequest;


class SetLeadsUserRequest extends APIBaseRequest
{
    protected $leads = [];


    public function rules()
    {
        return [
            'lead_id' => ['required', 'array'],
            // NO se valida, para poder mandar gran volumen de IDs. Se valida individualmente en el job
            // 'lead_id.*' => ['required', 'integer'],
        ];
    }

    public function withValidator($validator)
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);

        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $clientId = request()->input('client')->id;
                $correctClient = request()->newUser->client_id === $clientId;
                if (!$correctClient) {
                    $validator->errors()->add(
                        'client_id', 'New user client does not match with authenticated client'
                    );
                    return false;
                }

                // NO se valida, para poder mandar gran volumen de IDs. Se valida individualmente en el job
                // $leadIds = request()->input('lead_id');
                // $leadsCount = Lead::where('client_id', $clientId)->whereIn('id', $leadIds)->with(['user'])->count();
                // dd($leadsCount);
                // $leadIdsCount = count($leadIds);
                // if ($leadIdsCount != $leadsCount) {
                //     $validator->errors()->add('lead_id', 'Some leads do not exist');
                //     return false;
                // }
            }
        });
    }


    public function getLeadIds()
    {
        $validated = parent::validated();
        return $validated['lead_id'];
    }

}
