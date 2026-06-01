<?php

namespace App\Http\Controllers\API\Actions\ClientyConfigurations;

use App\Models\Client;
use App\Models\WapSalesAgentBot;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WapSalesAgent\WapSalesAgentBotService;
use App\Http\Requests\Actions\ClientyConfigurations\CreateWapSalesAgentBotRequest;
use App\Http\Requests\Actions\ClientyConfigurations\UpdateWapSalesAgentBotRequest;
use App\Http\Requests\Actions\ClientyConfigurations\DeleteWapSalesAgentBotRequest;


class WapSalesAgentBotController extends BaseAPIController
{

    public function create(Client $requestedClient, CreateWapSalesAgentBotRequest $req)
    {
        $bot = resolve(WapSalesAgentBotService::class)->create($req->validated());
        return $this->getSuccessResponse($bot);
    }


    public function update(
        Client $requestedClient,
        WapSalesAgentBot $requestedWapSalesAgentBot,
        UpdateWapSalesAgentBotRequest $req
    ) {
        $bot = resolve(WapSalesAgentBotService::class)->update($requestedWapSalesAgentBot, $req->validated());
        return $this->getSuccessResponse($bot);
    }


    public function delete(
        Client $requestedClient,
        WapSalesAgentBot $requestedWapSalesAgentBot,
        DeleteWapSalesAgentBotRequest $req
    ) {
        resolve(WapSalesAgentBotService::class)->delete($requestedWapSalesAgentBot);
        return $this->getSuccessResponse([]);
    }

}
