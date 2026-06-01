<?php

namespace App\Http\Resources\Views\Reports\UTMTraceReport;

use App\Http\Resources\LeadResource;
use Illuminate\Http\Resources\Json\JsonResource;


class UTMTraceReportItemResource extends JsonResource
{

    public function toArray($request)
    {
        $rs = new LeadResource($this->resource);
        $rs->setVisibleFields([
            'id',
            'company',
            'message',
            'quality',
            'utm_source',
            'utm_medium',
            'utm_content',
            'utm_campaign',
            'utm_keywords',
            'user',
            'landing',
            'quality',
            'status',
            'acquisitionChannel',
            'mainLeadContact',
            'created_at',
            'lead_created_at'
        ]);
        return $rs;
    }

}
