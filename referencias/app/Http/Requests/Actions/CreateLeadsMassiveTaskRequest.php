<?php

namespace App\Http\Requests\Actions;

use DateTime;
use DateTimeZone;
use App\Models\User;
use App\Models\Lead;
use App\Rules\InLeadReturnFields;
use App\Http\Requests\APIBaseRequest;


class CreateLeadsMassiveTaskRequest extends APIBaseRequest
{

    protected $leads = [];


    public function rules()
    {
        return [
            'lead_id' => ['required', 'array'],
            'lead_id.*' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:120'],
            'user_id' => ['required', 'integer'],
            'is_important' => ['required', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
            'limit_date' => ['required', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $userId = request()->input('user_id');
            $leadIds = request()->input('lead_id');
            $clientId = request()->input('client')->id;

            $user = User::where('client_id', $clientId)->where('id', $userId)->first();
            if (!$user) {
                $validator->errors()->add('user_id', 'User does not exist');
                return false;
            }

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


    public function getLeads()
    {
        return $this->leads;
    }


    public function getTaskData()
    {
        $validated = parent::validated();

        $date = (new DateTime($validated['limit_date']))->setTimezone(new DateTimeZone('UTC'));
        $validated['limit_date'] = $date->format('Y-m-d\TH:i:sP');

        return [
            'title' => $validated['title'],
            'user_id' => $validated['user_id'],
            'limit_date' => $validated['limit_date'],
            'description' => $validated['description'],
            'is_important' => $validated['is_important'],
        ];
    }

}
