<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\WhatsAppQuickResponse;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WhatsAppQuickResponseService;
use App\Http\Resources\WhatsAppQuickResponseResource;
use App\Http\Requests\GetWhatsAppQuickResponseRequest;
use App\Http\Requests\CreateWhatsAppQuickResponseRequest;
use App\Http\Requests\UpdateWhatsAppQuickResponseRequest;
use App\Http\Requests\DeleteWhatsAppQuickResponseRequest;
use App\Http\Resources\WhatsAppQuickResponseResourceCollection;


class WhatsAppQuickResponseController extends BaseAPIController
{

    public function list(Request $request)
    {
        $quickResponses = resolve(WhatsAppQuickResponseService::class)->findAllByClient();
        $rs = (new WhatsAppQuickResponseResourceCollection($quickResponses))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($rs);
    }


    public function getOne(WhatsAppQuickResponse $whatsAppQuickResponse, GetWhatsAppQuickResponseRequest $request)
    {
        $resource = (new WhatsAppQuickResponseResource($whatsAppQuickResponse))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($resource);
    }


    public function create(CreateWhatsAppQuickResponseRequest $request)
    {
        $quickResponse = resolve(WhatsAppQuickResponseService::class)->create($request->validatedAttributes());
        return $this->getSuccessResponse(
            (new WhatsAppQuickResponseResource($quickResponse))->loadOptionsFromRequest($request)
        );
    }


    public function update(WhatsAppQuickResponse $whatsAppQuickResponse, UpdateWhatsAppQuickResponseRequest $request)
    {
        $quickResponse = resolve(WhatsAppQuickResponseService::class)->update(
            $whatsAppQuickResponse, $request->validatedAttributes()
        );
        return $this->getSuccessResponse(
            (new WhatsAppQuickResponseResource($quickResponse))->loadOptionsFromRequest($request)
        );
    }


    public function delete(WhatsAppQuickResponse $whatsAppQuickResponse, DeleteWhatsAppQuickResponseRequest $request)
    {
        $quickResponse = resolve(WhatsAppQuickResponseService::class)->delete($whatsAppQuickResponse);
        return $this->getSuccessResponse(
            (new WhatsAppQuickResponseResource($quickResponse))->loadOptionsFromRequest($request)
        );
    }

}
