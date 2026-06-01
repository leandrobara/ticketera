<?php

namespace App\Http\Resources\Views\GmailEmail;

use App\Http\Resources\LeadResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class GmailEmailListItemResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $gmailEmailArr = $this->resource->toArray();

        $leadRs = new LeadResource($this->resource->lead);
        $leadRs->setVisibleFields(['id', 'status', 'acquisitionChannel', 'mainLeadContact']);
        $gmailEmailArr['lead'] = $leadRs;

        return $gmailEmailArr;
    }

}
