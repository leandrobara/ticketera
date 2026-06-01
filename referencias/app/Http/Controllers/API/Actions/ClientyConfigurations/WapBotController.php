<?php

namespace App\Http\Controllers\API\Actions\ClientyConfigurations;

use App\Models\Client;
use App\Models\WapBot;
use App\Services\API\WapBot\WapBotService;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WapBot\WapBotConversationService;
use App\Http\Requests\Actions\ClientyConfigurations\CreateWapBotRequest;
use App\Http\Requests\Actions\ClientyConfigurations\UpdateWapBotRequest;
use App\Http\Requests\Actions\ClientyConfigurations\DeleteWapBotRequest;
use App\Http\Requests\Actions\ClientyConfigurations\UploadWapBotSeedConversationsRequest;
use App\Http\Requests\Actions\ClientyConfigurations\CreatePermanentSeedConversationRequest;


class WapBotController extends BaseAPIController
{

    public function create(Client $requestedClient, CreateWapBotRequest $req)
    {
        $wapBot = resolve(WapBotService::class)->create($req->validated());
        return $this->getSuccessResponse($wapBot);
    }


    public function update(Client $requestedClient, WapBot $requestedWapBot, UpdateWapBotRequest $req)
    {
        $wapBot = resolve(WapBotService::class)->update($requestedWapBot, $req->validated());
        return $this->getSuccessResponse($wapBot);
    }


    public function delete(Client $requestedClient, WapBot $requestedWapBot, DeleteWapBotRequest $req)
    {
        resolve(WapBotService::class)->delete($requestedWapBot);
        return $this->getSuccessResponse([]);
    }


    public function uploadSeedConversations(
        Client $requestedClient,
        WapBot $requestedWapBot,
        UploadWapBotSeedConversationsRequest $req
    ) {
        $dto = $req->getDTO();
        $discardedRows = $req->getDiscardedRowsCount();
        
        $result = resolve(WapBotConversationService::class)->createUploadedSeedConversations(
            $requestedWapBot,
            $dto
        );

        return $this->getSuccessResponse([
            'createdCount' => $result['createdCount'],
            'updatedCount' => $result['updatedCount'],
            'skippedCount' => $result['skippedCount'],
            'discardedRows' => $discardedRows,
        ]);
    }


    public function createPermanentSeedConversation(
        Client $requestedClient,
        WapBot $requestedWapBot,
        CreatePermanentSeedConversationRequest $req
    ) {
        $customerPhoneNumber = $req->getCustomerPhoneNumber();

        $result = resolve(WapBotConversationService::class)->createOrUpdatePermanentSeedConversation(
            $requestedWapBot,
            $customerPhoneNumber
        );

        return $this->getSuccessResponse($result);
    }

}

