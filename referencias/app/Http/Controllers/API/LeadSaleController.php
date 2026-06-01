<?php

namespace App\Http\Controllers\API;

use App\Models\Lead;
use App\Models\LeadSale;
use App\Services\API\LeadSaleService;
use App\Http\Resources\LeadSaleResource;
use App\Http\Requests\GetLeadSaleRequest;
use App\Http\Requests\CreateLeadSaleRequest;
use App\Http\Requests\UpdateLeadSaleRequest;
use App\Http\Requests\DeleteLeadSaleRequest;
use App\Http\Controllers\API\BaseAPIController;


class LeadSaleController extends BaseAPIController
{

    public function getOne(Lead $lead, LeadSale $leadSale, GetLeadSaleRequest $request)
    {
        return $this->getSuccessResponse((new LeadSaleResource($leadSale))->loadOptionsFromRequest($request));
    }


    public function create(Lead $lead, CreateLeadSaleRequest $request)
    {
        $leadSale = resolve(LeadSaleService::class)->create($lead, $request->validatedAttributes());
        return $this->getSuccessResponse((new LeadSaleResource($leadSale))->loadOptionsFromRequest($request));
    }


    public function update(Lead $lead, LeadSale $leadSale, UpdateLeadSaleRequest $request)
    {
        $updatedLeadSale = resolve(LeadSaleService::class)->update($leadSale, $request->validatedAttributes());
        return $this->getSuccessResponse((new LeadSaleResource($updatedLeadSale))->loadOptionsFromRequest($request));
    }


    public function delete(Lead $lead, LeadSale $leadSale, DeleteLeadSaleRequest $request)
    {
        $deletedLeadSale = resolve(LeadSaleService::class)->delete($leadSale);
        return $this->getSuccessResponse((new LeadSaleResource($deletedLeadSale))->loadOptionsFromRequest($request));
    }

}
