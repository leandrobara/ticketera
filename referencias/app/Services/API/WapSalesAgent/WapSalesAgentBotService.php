<?php

namespace App\Services\API\WapSalesAgent;

use App\Models\WapSalesAgentBot;
use App\Repositories\WapSalesAgentBot\WapSalesAgentBotRepository;


class WapSalesAgentBotService
{

    public function __construct(
        protected readonly WapSalesAgentBotRepository $wapSalesAgentBotRepository
    ) {
    }


    public function find(int $id, array $opts = []): ?WapSalesAgentBot
    {
        return $this->wapSalesAgentBotRepository->find($id, $opts);
    }


    public function findActive(int $clientId, string $metaPhoneNumberId): ?WapSalesAgentBot
    {
        return $this->wapSalesAgentBotRepository->findActive($clientId, $metaPhoneNumberId);
    }


    public function findActiveByMetaPhoneNumberId(string $metaPhoneNumberId): ?WapSalesAgentBot
    {
        return $this->wapSalesAgentBotRepository->findActiveByMetaPhoneNumberId($metaPhoneNumberId);
    }


    public function create(array $data): WapSalesAgentBot
    {
        return $this->wapSalesAgentBotRepository->create($data);
    }


    public function update(WapSalesAgentBot $bot, array $data): WapSalesAgentBot
    {
        return $this->wapSalesAgentBotRepository->update($bot, $data);
    }


    public function delete(WapSalesAgentBot $bot): bool
    {
        return $this->wapSalesAgentBotRepository->delete($bot);
    }

}
