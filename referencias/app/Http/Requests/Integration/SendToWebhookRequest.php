<?php

namespace App\Http\Requests\Integration;

use App\Models\Lead;
use Illuminate\Support\Str;
use App\Rules\StringOrNumeric;
use App\Http\Requests\APIBaseRequest;


class SendToWebhookRequest extends APIBaseRequest
{

    public $lead;

    public function rules()
    {
        return [
            'endpoint' => ['required', 'url'],
            'leadId' => ['required', 'integer'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $leadId = request()->input('leadId');
                $client = request()->input('client');
                $this->endpoint = request()->input('endpoint');

                $this->lead = Lead::findOrFail($leadId);
                if ($client->id != $this->lead->client_id) {
                    $validator->errors()->add('lead', 'lead_does_not_belong_to_client');
                    return false;
                }
            }
        });
    }

}
