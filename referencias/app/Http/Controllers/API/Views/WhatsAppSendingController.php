<?php

namespace App\Http\Controllers\API\Views;

use App\Models\WhatsAppSending;
use App\Models\WhatsAppSendingMessage;
use App\Exports\WhatsAppSendingExport;
use App\Services\API\WhatsAppSendingService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Views\ListWhatsAppSendingRequest;
use App\Http\Requests\Views\WhatsAppSendingModalRequest;
use App\Http\Requests\Views\WhatsAppSendingExportInfoRequest;
use App\Http\Resources\Views\WhatsAppSending\WhatsAppSendingModalResource;
use App\Http\Resources\Views\WhatsAppSending\WhatsAppSendingResourceCollection;


class WhatsAppSendingController extends BaseAPIController
{

    public function list(ListWhatsAppSendingRequest $req)
    {
        $wapSendings = resolve(WhatsAppSendingService::class)->list($req->validated());
        $rs = (new WhatsAppSendingResourceCollection($wapSendings))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($rs);
    }


    public function modal(WhatsAppSending $whatsAppSending, WhatsAppSendingModalRequest $req)
    {
        return $this->getSuccessResponse(new WhatsAppSendingModalResource($whatsAppSending));
    }


    public function exportInfo(WhatsAppSending $whatsAppSending, WhatsAppSendingExportInfoRequest $req)
    {
        $whatsAppSendingMessages = resolve(WhatsAppSendingService::class)->listWhatsAppSendingMessagesToExport(
            $whatsAppSending, $req->validated()
        );
        return (new WhatsAppSendingExport($whatsAppSendingMessages))->download('reporte-envio-whatsapp.xlsx');
    }

}
