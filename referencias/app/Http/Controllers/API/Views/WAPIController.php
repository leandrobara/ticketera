<?php

namespace App\Http\Controllers\API\Views;

use App\Helpers\SystemHelper;
use App\Models\LeadContactPhone;
use App\Services\API\WAPIService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Views\WAPIListChatsRequest;
use App\Http\Requests\Views\WAPIListMessagesRequest;
use App\Http\Requests\Views\WAPIMessageModalRequest;
use App\Http\Resources\Views\WAPI\WAPIMessageModalResource;
use App\Http\Resources\Views\WAPI\WAPIChatResourceCollection;
use App\Http\Requests\Views\WAPIGetChatMessageMediaInfoRequest;
use App\Http\Resources\Views\WAPI\WAPIChatMessageResourceCollection;


class WAPIController extends BaseAPIController
{
    
    public function listChats(WAPIListChatsRequest $req)
    {
        SystemHelper::setTimeLimit(170);
        SystemHelper::setMemoryLimitMB(500);
        $wapiChatDTOs = resolve(WAPIService::class)->listChats($req->user, $req->validated());
        return $this->getSuccessResponse(new WAPIChatResourceCollection($wapiChatDTOs));
    }

    
    public function listChatMessages(LeadContactPhone $leadContactPhone, WAPIListMessagesRequest $req)
    {
        SystemHelper::setTimeLimit(170);
        SystemHelper::setMemoryLimitMB(500);
        $wapiChatMessagesDTOs = resolve(WAPIService::class)->listChatMessages($leadContactPhone, $req->validated());
        return $this->getSuccessResponse(new WAPIChatMessageResourceCollection($wapiChatMessagesDTOs));
    }


    public function getChatMessageMediaInfo(
        LeadContactPhone $leadContactPhone,
        string $wapiChatMessageId,
        WAPIGetChatMessageMediaInfoRequest $req
    ) {
        SystemHelper::setTimeLimit(120);
        $wapiChatMessageMediaInfo = resolve(WAPIService::class)->getChatMessageMediaInfo(
            $leadContactPhone, $wapiChatMessageId
        );
        return $this->getSuccessResponse($wapiChatMessageMediaInfo);
    }


    public function newMessageModal(WAPIMessageModalRequest $req)
    {
        $whatsAppMassiveModalDTO = resolve(WAPIService::class)->getMessageModalInfo($req->validatedLeadIds());
        return $this->getSuccessResponse(new WAPIMessageModalResource($whatsAppMassiveModalDTO));
    }

}
