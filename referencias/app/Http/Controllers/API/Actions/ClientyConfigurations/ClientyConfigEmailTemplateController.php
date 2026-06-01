<?php

namespace App\Http\Controllers\API\Actions\ClientyConfigurations;

use App\Models\ClientyConfigEmailTemplate;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\ClientyConfigEmailTemplateService;
use App\Http\Resources\ClientyConfigEmailTemplateResource;
use App\Http\Resources\ClientyConfigEmailTemplateResourceCollection;
use App\Http\Requests\Actions\CreateClientyConfigEmailTemplateRequest;
use App\Http\Requests\Actions\UpdateClientyConfigEmailTemplateRequest;
use App\Http\Requests\Actions\DeleteClientyConfigEmailTemplateRequest;


class ClientyConfigEmailTemplateController extends BaseAPIController
{

    public function create(CreateClientyConfigEmailTemplateRequest $req)
    {
        $emailTemplate = resolve(ClientyConfigEmailTemplateService::class)->create($req->validated());
        return $this->getSuccessResponse(
            (new ClientyConfigEmailTemplateResource($emailTemplate))->loadOptionsFromRequest($req)
        );
    }


    public function update(ClientyConfigEmailTemplate $emailTemplate, UpdateClientyConfigEmailTemplateRequest $req)
    {
        $emailTemplate = resolve(ClientyConfigEmailTemplateService::class)
            ->update($emailTemplate, $req->validated())
        ;
        return $this->getSuccessResponse(
            (new ClientyConfigEmailTemplateResource($emailTemplate))->loadOptionsFromRequest($req)
        );
    }


    public function delete(ClientyConfigEmailTemplate $emailTemplate, DeleteClientyConfigEmailTemplateRequest $req)
    {
        $emailTemplate = resolve(ClientyConfigEmailTemplateService::class)->delete($emailTemplate);
        return $this->getSuccessResponse(
            (new ClientyConfigEmailTemplateResource($emailTemplate)
        )->loadOptionsFromRequest($req));
    }
}
