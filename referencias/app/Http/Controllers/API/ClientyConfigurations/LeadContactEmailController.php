<?php

namespace App\Http\Controllers\API\ClientyConfigurations;

use App\Models\Client;
use Illuminate\Http\Request;
use App\Services\API\LeadContactEmailService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Resources\LeadContactEmailResource;
use App\Http\Requests\ClientyConfigurations\LeadContactEmail\ListLeadContactEmailByEmailRequest;


class LeadContactEmailController extends BaseAPIController
{

    public function getFirstOneByEmail(Client $requestedClient, ListLeadContactEmailByEmailRequest $req)
    {
        $leadContactEmail = resolve(LeadContactEmailService::class)->findFirstOneByClientAndEmail(
            $requestedClient, $req->input('email')
        );
        $rs = $leadContactEmail ? (new LeadContactEmailResource($leadContactEmail)) : null;
        return $this->getSuccessResponse($rs);
    }

}
