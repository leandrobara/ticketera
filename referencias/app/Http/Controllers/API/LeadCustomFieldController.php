<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\LeadCustomField;
use App\Http\Requests\GetLandingRequest;
use App\Services\API\LeadCustomFieldService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Resources\LeadCustomFieldResource;
use App\Http\Requests\GetLeadCustomFieldRequest;
use App\Http\Requests\UpdateLeadCustomFieldRequest;
use App\Http\Requests\DeleteLeadCustomFieldRequest;
use App\Http\Requests\CreateLeadCustomFieldRequest;
use App\Http\Requests\OrderUpLeadCustomFieldRequest;
use App\Http\Requests\OrderDownLeadCustomFieldRequest;
use App\Http\Resources\LeadCustomFieldResourceCollection;


class LeadCustomFieldController extends BaseAPIController
{

    public function list(Request $req)
    {
        $leadCustomFields = resolve(LeadCustomFieldService::class)->findAllByClient();
        $rs = (new LeadCustomFieldResourceCollection($leadCustomFields))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($rs);
    }

    
    public function create(CreateLeadCustomFieldRequest $req)
    {
        $leadCustomField = resolve(LeadCustomFieldService::class)->create($req->validatedAttributes());
        return $this->getSuccessResponse(
            (new LeadCustomFieldResource($leadCustomField))->loadOptionsFromRequest($req)
        );
    }


    public function getOne(LeadCustomField $leadCustomField, GetLeadCustomFieldRequest $req)
    {
        $rs = (new LeadCustomFieldResource($leadCustomField))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($rs);
    }


    public function update(LeadCustomField $leadCustomField, UpdateLeadCustomFieldRequest $req)
    {
        $updatedLeadCustomField = resolve(LeadCustomFieldService::class)->update(
            $leadCustomField, $req->validatedAttributes()
        );
        $rs = (new LeadCustomFieldResource($updatedLeadCustomField))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($rs);
    }


    public function delete(LeadCustomField $leadCustomField, DeleteLeadCustomFieldRequest $req)
    {
        $response = resolve(LeadCustomFieldService::class)->delete($leadCustomField);
        return $this->getSuccessResponse((new LeadCustomFieldResource($response))->loadOptionsFromRequest($req));
    }


    public function orderUp(LeadCustomField $leadCustomField, OrderUpLeadCustomFieldRequest $req)
    {
        $direction = 'up';
        $statusCategory = resolve(LeadCustomFieldService::class)->changeOrder($leadCustomField, $direction);
        return $this->getSuccessResponse((new LeadCustomFieldResource($leadCustomField))->loadOptionsFromRequest($req));
    }


    public function orderDown(LeadCustomField $leadCustomField, OrderDownLeadCustomFieldRequest $req)
    {
        $direction = 'down';
        $statusCategory = resolve(LeadCustomFieldService::class)->changeOrder($leadCustomField, $direction);
        return $this->getSuccessResponse((new LeadCustomFieldResource($leadCustomField))->loadOptionsFromRequest($req));
    }

}
