<?php

namespace App\Http\Controllers\API\Actions\ClientyConfigurations;

use App\Models\ClientyConfigWhatsAppTemplate;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\ClientyConfigWhatsAppTemplateService;
use App\Http\Resources\ClientyConfigWhatsAppTemplateResource;
use App\Http\Requests\Actions\UpdateClientyConfigWhatsAppTemplateRequest;
use App\Http\Requests\Actions\DeleteClientyConfigWhatsAppTemplateRequest;
use App\Http\Requests\Actions\CreateClientyConfigWhatsAppTemplateRequest;


class ClientyConfigWhatsAppTemplateController extends BaseAPIController
{

    public function create(CreateClientyConfigWhatsAppTemplateRequest $req)
    {
        $wapTemplate = resolve(ClientyConfigWhatsAppTemplateService::class)->create($req->validated());
        $rs = (new ClientyConfigWhatsAppTemplateResource($wapTemplate))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($rs);
    }


    public function update(
        ClientyConfigWhatsAppTemplate $wapTemplate,
        UpdateClientyConfigWhatsAppTemplateRequest $req
    ) {
        $wapTemplate = resolve(ClientyConfigWhatsAppTemplateService::class)->update($wapTemplate, $req->validated());
        $rs = (new ClientyConfigWhatsAppTemplateResource($wapTemplate))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($rs);
    }


    public function delete(
        ClientyConfigWhatsAppTemplate $wapTemplate,
        DeleteClientyConfigWhatsAppTemplateRequest $req
    ) {
        $wapTemplate = resolve(ClientyConfigWhatsAppTemplateService::class)->delete($wapTemplate);
        $rs = (new ClientyConfigWhatsAppTemplateResource($wapTemplate))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($rs);
    }

}
