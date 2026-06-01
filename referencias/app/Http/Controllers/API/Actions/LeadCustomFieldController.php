<?php

namespace App\Http\Controllers\API\Actions;

use App\Models\Lead;
use Illuminate\Http\Request;
use App\Models\LeadCustomField;
use App\Services\API\LeadCustomFieldService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Resources\LeadCustomFieldResource;
use App\Http\Requests\SetLeadCustomFieldValueRequest;


class LeadCustomFieldController extends BaseAPIController
{

    public function setValue(Lead $lead, LeadCustomField $leadCustomField, SetLeadCustomFieldValueRequest $request)
    {
        $leadCustomField = resolve(LeadCustomFieldService::class)->setValue(
            $lead, $leadCustomField, $request->getValueStr()
        );
        $rs = (new LeadCustomFieldResource($leadCustomField));
        $rs->setVisibleFields([
            'id',
            'name',
            'type',
            'order',
            'type_values',
            'default_value',
            'is_shown_in_leads_row',
            'leadCustomFieldValue'
        ]);
        $rs->setLeadCustomFieldValue($leadCustomField->getLeadCustomFieldValueByLead($lead));
        return $this->getSuccessResponse($rs);
    }

}
