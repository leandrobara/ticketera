<?php

namespace App\Http\Resources\Integration;

use App\Models\Lead;
use Illuminate\Support\Collection;
use App\Http\Resources\Integration\LeadResource;


class WebhookLeadResource
{

    private $lead = null;
    private $triggerCode = null;


    public function __construct(Lead $lead, string $triggerCode)
    {
        $this->lead = $lead;
        $this->triggerCode = $triggerCode;
    }


    public function toArray()
    {
        $leadArr = (new LeadResource($this->lead))->toArray();
        $response = [
            'triggerCode' => $this->triggerCode,
            'lead' => $leadArr,
        ];
        return $response;
    }

}
