<?php

namespace App\Http\Controllers\API\Actions;

use Exception;
use App\Models\Lead;
use App\Helpers\SystemHelper;
use App\Models\WhatsAppSending;
use App\Models\LeadContactEmail;
use App\Services\API\WAPIService;
use App\Services\API\WhatsAppSendingService;
use App\Http\Resources\WhatsAppSendingResource;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Actions\WAPI\WAPISendMessageRequest;
use App\Http\Requests\Actions\WAPI\WAPICancelSendingRequest;
use App\Http\Requests\Actions\WAPI\WAPIScheduleMessageRequest;
use App\Http\Requests\Actions\WAPI\WAPIDeleteSessionFilesRequest;


// @todo: cambiar "message" por "sending"
class WAPIController extends BaseAPIController
{

    public function sendMessage(WAPISendMessageRequest $req)
    {
        SystemHelper::setTimeLimit(120);
        $whatsAppSending = resolve(WAPIService::class)->createNewSending($req->dto());
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    public function scheduleMessage(WAPIScheduleMessageRequest $req)
    {
        SystemHelper::setTimeLimit(120);
        $whatsAppSending = resolve(WAPIService::class)->createNewSending($req->dto());
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    public function cancelSending(WhatsAppSending $whatsAppSending, WAPICancelSendingRequest $req)
    {
        $whatsAppSending = resolve(WhatsAppSendingService::class)->cancel($whatsAppSending);
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    // Borra los archivos de sesión del servidor de WAPI
    public function deleteSessionFiles(WAPIDeleteSessionFilesRequest $req)
    {
        $success = resolve(WAPIService::class)->deleteSessionFiles($req->phoneNumber);
        return $this->getSuccessResponse(['wasExistentSession' => $success]);
    }

}
