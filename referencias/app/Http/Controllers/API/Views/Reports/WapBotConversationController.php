<?php

namespace App\Http\Controllers\API\Views\Reports;

use App\Services\Traits\GetClientFromRequest;
use App\Http\Controllers\API\BaseAPIController;
use App\Models\MongoDB\WapBot\WapBotConversation;
use App\Services\API\WapBot\WapBotConversationService;
use App\Http\Requests\Views\Reports\ListWapBotConversationRequest;
use App\Http\Resources\Views\Reports\WapBotConversationItemResource;
use App\Http\Requests\Views\Reports\WapBotConversationModalRequest;
use App\Http\Resources\Views\Reports\WapBotConversationModalResource;


class WapBotConversationController extends BaseAPIController
{

    use GetClientFromRequest;


    public function list(ListWapBotConversationRequest $req)
    {
        $conversations = resolve(WapBotConversationService::class)->list($this->getClient(), $req->validated());
        return $this->getSuccessResponse([
            'conversations' => WapBotConversationItemResource::collection($conversations->items()),
            'conversationsTotalCount' => $conversations->total(),
        ]);
    }


    // En RouteServiceProvider.php se configuró que traiga modelos withTrashed() también
    public function modal(WapBotConversationModalRequest $req, WapBotConversation $wapBotConversation)
    {
        $conversation = resolve(WapBotConversationService::class)->getModalInfo(
            $this->getClient(), $wapBotConversation
        );
        return $this->getSuccessResponse(new WapBotConversationModalResource($conversation));
    }

}

