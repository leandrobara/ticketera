<?php

namespace App\DTO\ClientyConfigurations;

use stdClass;
use App\Models\Client;


class ClientEmailSendingMetricsDTO
{

    public $sentCount = 0;
    public $client = null;
    public $openedCount = 0;
    public $bouncedCount = 0;
    public $complainedCount = 0;
    public $openedPercentage = 0;
    public $bouncedPercentage = 0;
    public $unsubscribedCount = 0;
    public $complainedPercentage = 0;
    public $unsubscribedPercentage = 0;
    

    public static function buildFromQueryResult(Client $client, ?stdClass $metricsObj): ClientEmailSendingMetricsDTO
    {
        $dto = new ClientEmailSendingMetricsDTO();

        $dto->client = $client;
        if ($metricsObj) {
            $dto->sentCount = (int) $metricsObj->sentCount;
            $dto->openedCount = (int) $metricsObj->openedCount;
            $dto->bouncedCount = (int) $metricsObj->bouncedCount;
            $dto->complainedCount = (int) $metricsObj->complainedCount;
            $dto->unsubscribedCount = (int) $metricsObj->unsubscribedCount;
            if ($dto->sentCount) {
                $dto->openedPercentage = ($dto->openedCount * 100) / $dto->sentCount;
                $dto->bouncedPercentage = ($dto->bouncedCount * 100) / $dto->sentCount;
                $dto->complainedPercentage = ($dto->complainedCount * 100) / $dto->sentCount;
                $dto->unsubscribedPercentage = ($dto->unsubscribedCount * 100) / $dto->sentCount;
            }
        }
        
        return $dto;
    }


    public function toArray(): array
    {
        $arr = [
            'client_id' => $this->client->id,
            'sent_count' => $this->sentCount,
            'opened_count' => $this->openedCount,
            'bounced_count' => $this->bouncedCount,
            'complained_count' => $this->complainedCount,
            'opened_percentage' => $this->openedPercentage,
            'bounced_percentage' => $this->bouncedPercentage,
            'unsubscribed_count' => $this->unsubscribedCount,
            'complained_percentage' => $this->complainedPercentage,
            'unsubscribed_percentage' => $this->unsubscribedPercentage,
        ];
        return $arr;
    }

}
