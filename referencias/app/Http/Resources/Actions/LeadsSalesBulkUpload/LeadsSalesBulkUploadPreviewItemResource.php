<?php

namespace App\Http\Resources\Actions\LeadsSalesBulkUpload;

use App\Http\Resources\UserResource;
use App\Http\Resources\StatusResource;
use App\Http\Resources\TagResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\Http\Resources\AcquisitionChannelResource;
use App\Http\Resources\LeadContactResourceCollection;


class LeadsSalesBulkUploadPreviewItemResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = [
            'leadId' => $this->resource->leadId,
            'amount' => $this->resource->amount,
            'saleDate' => $this->resource->saleDate,
            'description' => $this->resource->description,

            'leadWasFound' => $this->resource->leadWasFound,
            'leadIdIsEmpty' => $this->resource->leadIdIsEmpty,
            'amountIsEmpty' => $this->resource->amountIsEmpty,
            'saleDateIsEmpty' => $this->resource->saleDateIsEmpty,
            'amountFormatIsWrong' => $this->resource->amountFormatIsWrong,
            'descriptionIsTooLong' => $this->resource->descriptionIsTooLong,
            'salesDateFormatIsWrong' => $this->resource->salesDateFormatIsWrong,
            
            'warningReasons' => $this->resource->warningReasons,
            'nonPersistableReasons' => $this->resource->nonPersistableReasons,
        ];
        return $response;
    }

}
