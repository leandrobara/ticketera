<?php

namespace App\Http\Controllers\API\Views;

use App\Helpers\SystemHelper;
use App\Models\LeadContactPhone;
use App\Services\API\WAPSenderService;
use Illuminate\Support\Facades\Request;
use App\DTO\WAPSender\WAPSenderChatMessageDTO;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Views\WAPSender\WAPSenderMessageModalRequest;
use App\Http\Requests\Views\WAPSender\WAPSenderListMessagesRequest;
use App\Http\Resources\Views\WAPSender\WAPSenderMessageModalResource;
use App\Http\Requests\Views\WAPSender\WAPSenderGetSendingQuotaByDateRequest;
use App\Http\Requests\Views\WAPSender\WAPSenderGetChatMessageMediaInfoRequest;


class WAPSenderController extends BaseAPIController
{
    
    public function newMessageModal(WAPSenderMessageModalRequest $req)
    {
        $whatsAppMassiveModalDTO = resolve(WAPSenderService::class)->getMessageModalInfo($req->validatedLeadIds());
        return $this->getSuccessResponse(new WAPSenderMessageModalResource($whatsAppMassiveModalDTO));
    }


    public function getSendingQuotaByDate(WAPSenderGetSendingQuotaByDateRequest $req)
    {
        // ['dailyUserQuota' => xx, 'dailyUsedQuota' => xx]
        $userQuotaInfo = resolve(WAPSenderService::class)->getSendingQuotaInfoByUserAndDate(
            $req->user, $req->getSendDate()
        );
        return $this->getSuccessResponse($userQuotaInfo);
    }


    public function listChatMessages(LeadContactPhone $leadContactPhone, WAPSenderListMessagesRequest $req)
    {
        SystemHelper::setTimeLimit(45);
        SystemHelper::setMemoryLimitMB(500);

        $chatMessages = resolve(WAPSenderService::class)->listChatMessages($leadContactPhone);
        $dtos = $chatMessages->map(fn ($chatMsg) => new WAPSenderChatMessageDTO($chatMsg));
        return $this->getSuccessResponse($dtos);
    }


    public function getChatMessageMediaInfo(
        LeadContactPhone $leadContactPhone,
        string $chatMessageId,
        WAPSenderGetChatMessageMediaInfoRequest $req
    ) {
        SystemHelper::setTimeLimit(120);
        $wapSenderChatMessageMediaInfo = resolve(WAPSenderService::class)->getChatMessageMediaInfo(
            $leadContactPhone, $chatMessageId
        );
        return $this->getSuccessResponse($wapSenderChatMessageMediaInfo);
    }

}
