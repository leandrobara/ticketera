<?php

namespace App\Services\API;

use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\WhatsAppQuickResponse;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;


class WhatsAppQuickResponseService
{

    use GetClientFromRequest, GetUserFromRequest;

    private const TITLE_MAX_LENGTH = 100;
    private const BODY_MAX_LENGTH = 1000;


    public function __construct(
        protected Repository $whatsAppQuickResponseRepository,
    ) {
    }


    public function findWhatsAppQuickResponseById(int $id): ?WhatsAppQuickResponse
    {
        return $this->whatsAppQuickResponseRepository->findById($id);
    }


    public function findAllByClient(): Collection
    {
        return $this->whatsAppQuickResponseRepository->findAllByClient($this->getClient());
    }


    public function create(array $data): WhatsAppQuickResponse
    {
        $data['client_id'] = $data['client_id'] ?? $this->getClient()->id;
        $data = $this->truncateLengthRestrictedFields($data);
        return $this->whatsAppQuickResponseRepository->create($data);
    }


    public function update(WhatsAppQuickResponse $quickResponse, array $data): WhatsAppQuickResponse
    {
        $data = $this->truncateLengthRestrictedFields($data);
        return $this->whatsAppQuickResponseRepository->update($quickResponse, $data);
    }


    public function delete(WhatsAppQuickResponse $quickResponse): WhatsAppQuickResponse
    {
        return $this->whatsAppQuickResponseRepository->delete($quickResponse);
    }


    /**
     * Trunca silenciosamente title y body si exceden los límites permitidos,
     * para evitar errores 400 cuando el cliente envía valores más largos.
     */
    private function truncateLengthRestrictedFields(array $data): array
    {
        if (isset($data['title'])) {
            $data['title'] = mb_substr($data['title'], 0, self::TITLE_MAX_LENGTH);
        }
        if (isset($data['body'])) {
            $data['body'] = mb_substr($data['body'], 0, self::BODY_MAX_LENGTH);
        }
        return $data;
    }

}
