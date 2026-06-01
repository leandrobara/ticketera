<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Models\WhatsAppTemplate;
use App\Services\API\WhatsAppTemplateService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Resources\WhatsAppTemplateResource;
use App\Http\Requests\GetWhatsAppTemplateRequest;
use App\Http\Requests\DeleteWhatsAppTemplateRequest;
use App\Http\Requests\CreateWhatsAppTemplateRequest;
use App\Http\Requests\UpdateWhatsAppTemplateRequest;
use App\Http\Resources\WhatsAppTemplateResourceCollection;


class WhatsAppTemplateController extends BaseAPIController
{

    public function list(Request $request)
    {
        $tpls = resolve(WhatsAppTemplateService::class)->findAllByClient();
        $rs = (new WhatsAppTemplateResourceCollection($tpls))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($rs);
    }


    public function getOne(WhatsAppTemplate $whatsAppTemplate, GetWhatsAppTemplateRequest $request)
    {
        $resource = (new WhatsAppTemplateResource($whatsAppTemplate))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function create(CreateWhatsAppTemplateRequest $request)
    {
        SystemHelper::setTimeLimit(60); // A veces META tarda
        $whatsAppTemplate = resolve(WhatsAppTemplateService::class)->create($request->validatedAttributes());
        return $this->getSuccessResponse(
            (new WhatsAppTemplateResource($whatsAppTemplate))->loadOptionsFromRequest($request)
        );
    }


    public function update(WhatsAppTemplate $whatsAppTemplate, UpdateWhatsAppTemplateRequest $request)
    {
        $whatsAppTemplate = resolve(WhatsAppTemplateService::class)->update(
            $whatsAppTemplate, $request->validatedAttributes()
        );
        return $this->getSuccessResponse(
            (new WhatsAppTemplateResource($whatsAppTemplate))->loadOptionsFromRequest($request)
        );
    }


    public function delete(WhatsAppTemplate $whatsAppTemplate, DeleteWhatsAppTemplateRequest $request)
    {
        $whatsAppTemplate = resolve(WhatsAppTemplateService::class)->delete($whatsAppTemplate);

        return $this->getSuccessResponse(
            (new WhatsAppTemplateResource($whatsAppTemplate))->loadOptionsFromRequest($request)
        );
    }

}
