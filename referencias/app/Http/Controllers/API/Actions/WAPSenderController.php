<?php

namespace App\Http\Controllers\API\Actions;

use Exception;
use App\Models\Lead;
use App\Helpers\SystemHelper;
use App\Models\WhatsAppSending;
use App\Models\LeadContactEmail;
use App\Services\API\WAPSenderService;
use App\Services\API\WhatsAppSendingService;
use App\Http\Resources\WhatsAppSendingResource;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Actions\WAPSender\WAPSenderSendMessageRequest;
use App\Http\Requests\Actions\WAPSender\WAPSenderCancelSendingRequest;
use App\Http\Requests\Actions\WAPSender\WAPSenderScheduleMessageRequest;

class WAPSenderController extends BaseAPIController
{

    public function sendMessage(WAPSenderSendMessageRequest $req)
    {
        $whatsAppSending = resolve(WAPSenderService::class)->createNewSending($req->dto());
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    public function scheduleMessage(WAPSenderScheduleMessageRequest $req)
    {
        $whatsAppSending = resolve(WAPSenderService::class)->createNewSending($req->dto());
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }


    public function cancelSending(WhatsAppSending $whatsAppSending, WAPSenderCancelSendingRequest $req)
    {
        $whatsAppSending = resolve(WhatsAppSendingService::class)->cancel($whatsAppSending);
        return $this->getSuccessResponse(new WhatsAppSendingResource($whatsAppSending));
    }

}
