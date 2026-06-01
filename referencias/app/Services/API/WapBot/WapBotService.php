<?php

namespace App\Services\API\WapBot;

use App\Models\WapBot;
use Illuminate\Support\Collection;
use App\Repositories\WapBot\WapBotRepository;
use App\Repositories\Cache\WapBotRepositoryCache;


class WapBotService
{

    public function __construct(
        protected readonly WapBotRepository | WapBotRepositoryCache $wapBotRepository
    ) {
    }


    public function find(int $wapBotId, array $opts = []): ?WapBot
    {
        return $this->wapBotRepository->find($wapBotId, $opts);
    }


    public function findActive(int $clientId, string $connectedMetaPhoneNumberId): ?WapBot
    {
        return $this->wapBotRepository->findActive($clientId, $connectedMetaPhoneNumberId);
    }


    public function findById(int $wapBotId): ?WapBot
    {
        return $this->wapBotRepository->findById($wapBotId);
    }


    public function findActiveByMetaPhoneNumberId(string $metaPhoneNumberId): ?WapBot
    {
        return $this->wapBotRepository->findActiveByMetaPhoneNumberId($metaPhoneNumberId);
    }


    public function findEnabledWithFollowUp(): Collection
    {
        return $this->wapBotRepository->findEnabledWithFollowUp();
    }


    public function findEnabledWithEnabledLeadsAutoCreation(): Collection
    {
        return $this->wapBotRepository->findEnabledWithEnabledLeadsAutoCreation();
    }


    public function create(array $data): WapBot
    {
        return $this->wapBotRepository->create($data);
    }


    public function update(WapBot $wapBot, array $data): WapBot
    {
        // Borrar el WapBot actual (soft delete)
        $this->wapBotRepository->delete($wapBot);

        // Crear uno nuevo con los datos editados
        return $this->wapBotRepository->create($data);
    }


    public function delete(WapBot $wapBot): bool
    {
        return $this->wapBotRepository->delete($wapBot);
    }

}
