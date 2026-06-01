<?php

namespace App\Http\Resources\Views\WhatsAppSending;

use App\Http\Resources\LeadContactResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\LeadContactPhoneResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WhatsAppSendingMessageResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();
        if (in_array('mainLeadContact', $visibleFields)) {
            $response = $this->loadMainLeadContactField($response);
        }
        if (in_array('leadContactPhone', $visibleFields)) {
            $response = $this->loadLeadContactPhoneField($response);
        }
        
        $response = $this->loadWAutomationLogField($response);

        $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadMainLeadContactField(array $response)
    {
        if (!$this->resource->lead) {
            $response['mainLeadContact'] = null;
            return $response;
        }
        $leadContact = $this->resource->lead->mainLeadContact;
        $visibleFields = ['id', 'name', 'last_name', 'role'];
        $rs = new LeadContactResource($leadContact);
        $rs->setVisibleFields($visibleFields);
        $response['mainLeadContact'] = $rs;
        return $response;
    }


    private function loadLeadContactPhoneField(array $response)
    {
        if (!$this->resource->lead) {
            $response['leadContactPhone'] = null;
            return $response;
        }
        $leadContact = $this->resource->leadContactPhone;
        $visibleFields = ['id', 'lead_id', 'lead_contact_id', 'phone'];
        $rs = new LeadContactPhoneResource($leadContact);
        $rs->setVisibleFields($visibleFields);
        $response['leadContactPhone'] = $rs;
        return $response;
    }


    private function loadWAutomationLogField(array $response)
    {
        if (!$this->resource->relationLoaded('wAutomationLog')) {
            $this->resource->load('wAutomationLog');
        }

        if (!$this->resource->wAutomationLog) {
            $response['wAutomationLog'] = null;
            return $response;
        }

        $wAutomationLog = $this->resource->wAutomationLog;

        if (!$wAutomationLog->relationLoaded('wAutomationSequence')) {
            $wAutomationLog->load('wAutomationSequence');
        }

        $response['wAutomationLog'] = $wAutomationLog->attributesToArray();
        $response['wAutomationLog']['wAutomationSequence'] = $wAutomationLog->wAutomationSequence;

        return $response;
    }

}
