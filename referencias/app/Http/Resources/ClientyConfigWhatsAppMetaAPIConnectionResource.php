<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class ClientyConfigWhatsAppMetaAPIConnectionResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->toArray();
        $response['wapBot'] = $response['wap_bot'];
        $response['wapSalesAgentBot'] = $response['wap_sales_agent_bot'] ?? null;
        if ($response['wapSalesAgentBot'] && $this->resource->wapSalesAgentBot?->user) {
            $response['wapSalesAgentBot']['userName'] = $this->resource->wapSalesAgentBot->user->name;
        }

        if ($response['wapBot']) {
            // unset($response['wapBot']['prompt']);
            $response['wapBot']['seedConversationsCount'] = $this->resource->wapBot->seedConversationsCount;
        }

        unset($response['wap_bot']);
        unset($response['access_token']);
        unset($response['wap_sales_agent_bot']);
        return $response;
    }

}
