<?php

namespace App\Http\Requests\Integration;

use App\Models\Status;
use App\Services\API\StatusService;
use App\Http\Requests\APIBaseRequest;


class ChangeLeadStatusFromMakeAppRequest extends APIBaseRequest
{

    protected $status;

    public function rules()
    {
        return [];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $statusName = request()->statusName;
            $client = request()->input('client');
            
            $status = resolve(StatusService::class)->findOneByClientAndName($client, $statusName);
            if (!$status) {
                $validator->errors()->add('status', 'Status does not exist');
                return false;
            }
            if ($status->client_id != $client->id) {
                $validator->errors()->add('client_id', 'Status client does not match with authenticated client');
                return false;
            }
            $this->status = $status;
        });
    }


    public function getNewStatus(): Status
    {
        return $this->status;
    }

}
