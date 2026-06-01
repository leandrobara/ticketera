<?php

namespace App\DTO\WAPI;


class WAPISyncStatusDTO
{

    private function __construct(
        public array|null $qrCode = null,
        public bool $isReady = false,
        public bool $isAuthenticated = false
    ) {
    }


    public static function buildEmpty(): WAPISyncStatusDTO
    {
        return new WAPISyncStatusDTO();
    }


    public static function buildFromWapiResponse(array $wapiResponse): WAPISyncStatusDTO
    {
        $dto = new WAPISyncStatusDTO();
        if (!$wapiResponse['data']) {
            return $dto;
        }
        $dto->isReady = (bool) $wapiResponse['data']['isReady'];
        $dto->isAuthenticated = (bool) $wapiResponse['data']['isAuthenticated'];
        $qrCode = $wapiResponse['data']['qrCode'] ?? null;
        if ($qrCode) {
            $dto->qrCode = [
                'text' => $qrCode['text'],
                'svgImageString' => $qrCode['svgImageString'],
            ];
        }
        return $dto;
    }


    public function toArray(): array
    {
        return [
            'qrCode' => $this->qrCode,
            'isReady' => $this->isReady,
            'isAuthenticated' => $this->isAuthenticated,
        ];
    }

}
