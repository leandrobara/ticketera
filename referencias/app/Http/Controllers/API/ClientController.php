<?php

namespace App\Http\Controllers\API;

use App\Models\Client;
use Illuminate\Http\Request;
use App\Services\API\ClientService;
use App\Http\Requests\ListClientRequest;
use App\Http\Resources\ClientResourceCollection;


// @todo Se usa esto??
class ClientController extends BaseAPIController
{

    public function list(ListClientRequest $request)
    {
        $clients = resolve(ClientService::class)->findAllEnabled();
        $rs = (new ClientResourceCollection($clients))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($rs);
    }

}
