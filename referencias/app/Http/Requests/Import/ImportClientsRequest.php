<?php

namespace App\Http\Requests\Import;

use App\Http\Requests\APIBaseRequest;


class ImportClientsRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'leads_client_id' =>  ['sometimes', 'integer'],
        ];
    }

}
