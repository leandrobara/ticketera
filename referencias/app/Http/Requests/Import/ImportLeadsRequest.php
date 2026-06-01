<?php

namespace App\Http\Requests\Import;

use App\Models\Client;
use App\Http\Requests\APIBaseRequest;


class ImportLeadsRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'limit' =>  ['sometimes', 'integer'],
            'offset' =>  ['sometimes', 'integer'],
            'client_id' =>  ['sometimes', 'integer'],
            'leads_lead_id' =>  ['sometimes', 'integer'],
        ];
    }


    public function getRequestParams(): array
    {
        $params = $this->validated();

        if ($params['client_id'] ?? null) {
            $client = Client::findOrFail($params['client_id']);
            unset($params['client_id']);
            $params['leads_client_id'] = $client->leads_client_id;
        }
        
        return $params;
    }


    public function getRequestName(): string
    {
        return 'ImportLeadsRequest';
    }

}
