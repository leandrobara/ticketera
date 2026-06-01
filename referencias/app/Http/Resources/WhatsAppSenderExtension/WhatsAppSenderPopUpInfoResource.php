<?php

namespace App\Http\Resources\WhatsAppSenderExtension;

use App\Http\Resources\WhatsAppSendingResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\DTO\WhatsAppSenderExtension\WhatsAppSenderPopUpInfoDTO;


class WhatsAppSenderPopUpInfoResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function __construct(WhatsAppSenderPopUpInfoDTO $dto)
    {
        $this->resource = $dto;
    }


    public function toArray($request)
    {
        $lastSendingRs = new WhatsAppSendingResource($this->resource->lastSending);
        $currentSendingRs = new WhatsAppSendingResource($this->resource->currentSending);
        $response = [
            'lastSending' => $lastSendingRs,
            'currentSending' => $currentSendingRs,
            'dailyUsedQuota' => $this->resource->dailyUsedQuota ?? 0,
            'dailyUserQuota' => $this->resource->dailyUserQuota ?? 0,
            'quotaPerSending' => $this->resource->quotaPerSending ?? 0,
            'isVersionUpToDate' => $this->resource->isVersionUpToDate(),
            'dailyRemainingQuota' => $this->resource->dailyRemainingQuota ?? 0,
        ];
        return $response;
    }

}
