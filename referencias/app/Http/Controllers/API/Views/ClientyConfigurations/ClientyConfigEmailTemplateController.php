<?php

namespace App\Http\Controllers\API\Views\ClientyConfigurations;

use Illuminate\Http\Request;
use App\Models\ClientyConfigEmailTemplate;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\ClientyConfigEmailTemplateService;
use App\Http\Resources\ClientyConfigEmailTemplateResourceCollection;
use App\Http\Resources\Views\EmailTemplateModal\ClientyConfigEmailTemplateModalResource;
use App\Http\Requests\Views\ClientyConfigurations\EmailTemplate\ListClientyConfigEmailTemplateRequest;
use App\Http\Requests\Views\ClientyConfigurations\EmailTemplate\ModalClientyConfigEmailTemplateRequest;


class ClientyConfigEmailTemplateController extends BaseAPIController
{

    public function list(ListClientyConfigEmailTemplateRequest $req)
    {
        $emailTemplateList = resolve(ClientyConfigEmailTemplateService::class)->list($req->validated());
        $resource = (new ClientyConfigEmailTemplateResourceCollection($emailTemplateList))
            ->loadOptionsFromRequest($req)
        ;
        return $this->getSuccessResponse($resource);
    }


    public function modal(
        ClientyConfigEmailTemplate $clientyConfigEmailTemplate,
        ModalClientyConfigEmailTemplateRequest $req
    ) {
        return $this->getSuccessResponse(new ClientyConfigEmailTemplateModalResource($clientyConfigEmailTemplate));
    }

}
