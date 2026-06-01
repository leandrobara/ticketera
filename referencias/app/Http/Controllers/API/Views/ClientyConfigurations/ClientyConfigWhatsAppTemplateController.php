<?php

namespace App\Http\Controllers\API\Views\ClientyConfigurations;

use Illuminate\Http\Request;
use App\Models\ClientyConfigWhatsAppTemplate;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\ClientyConfigWhatsAppTemplateService;
use App\Http\Resources\ClientyConfigWhatsAppTemplateResourceCollection;
use App\Http\Resources\Views\WhatsAppTemplateModal\ClientyConfigWhatsAppTemplateModalResource;
use App\Http\Requests\Views\ClientyConfigurations\WhatsAppTemplate\ListClientyConfigWhatsAppTemplateRequest;
use App\Http\Requests\Views\ClientyConfigurations\WhatsAppTemplate\ModalClientyConfigWhatsAppTemplateRequest;


class ClientyConfigWhatsAppTemplateController extends BaseAPIController
{

    public function list(ListClientyConfigWhatsAppTemplateRequest $req)
    {
        $whatsAppTemplate = resolve(ClientyConfigWhatsAppTemplateService::class)->list($req->validated());
        $rs = (new ClientyConfigWhatsAppTemplateResourceCollection($whatsAppTemplate))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($rs);
    }


    public function modal(
        ClientyConfigWhatsAppTemplate $clientyConfigWhatsAppTemplate,
        ModalClientyConfigWhatsAppTemplateRequest $req
    ) {
        $rs = new ClientyConfigWhatsAppTemplateModalResource($clientyConfigWhatsAppTemplate);
        return $this->getSuccessResponse($rs);
    }

}
