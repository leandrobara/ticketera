<?php

namespace App\Http\Controllers\API\Views\Reports;

use Illuminate\Http\Request;
use App\Services\Traits\GetClientFromRequest;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WhatsAppMetaAPI\WhatsAppConversationMessageService;
use App\Helpers\WhatsAppMetaAPI\WhatsAppConversationRealTimeHelper;
use App\Http\Requests\Views\Reports\ListWhatsAppMetaAPIConversationRequest;


class WhatsAppMetaAPIConversationController extends BaseAPIController
{

    public function list(ListWhatsAppMetaAPIConversationRequest $req)
    {
        $conversations = resolve(WhatsAppConversationMessageService::class)->listConversations(
            $req->client, $req->user, $req->validated()
        );
        return $this->getSuccessResponse($conversations);
    }


    public function registerWhatsAppConversationsActiveViewer(Request $req)
    {
        resolve(WhatsAppConversationRealTimeHelper::class)->registerWhatsAppConversationsActiveViewer(
            $req->client->id
        );
        return $this->getSuccessResponse(true);
    }

}
