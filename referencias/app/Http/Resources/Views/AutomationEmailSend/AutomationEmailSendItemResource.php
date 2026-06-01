<?php

namespace App\Http\Resources\Views\AutomationEmailSend;

use App\Http\Resources\TagResourceCollection;
use App\Http\Resources\StatusResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Views\AutomationEmailSendStep\AutomationEmailSendStepCollectionResource;


class AutomationEmailSendItemResource extends JsonResource
{

    public function toArray($request)
    {
        $automation = $this->resource->toArray();
        $wasApplied = $this->resource?->automationLog ? true : false;

        $response = [
            'id' => $automation['id'],
            'was_applied' => $wasApplied,
            'name' => $automation['name'],
            'enabled' => $automation['enabled'],
            'updated_at' => $automation['updated_at'],
            'trigger_type' => $automation['trigger_type'],
            'do_not_send_weekends' => $automation['do_not_send_weekends'],
            'cancel_if_sequence_was_sent' => $automation['cancel_if_sequence_was_sent'],
        ];
        $response = $this->loadCancellingTags($response);
        $response = $this->loadCancellingStatus($response);
        $response = $this->loadTriggeringTags($response);
        $response = $this->loadTriggeringStatus($response);
        $response = $this->loadSendMessageSteps($response);
        return $response;
    }


    private function loadCancellingTags($response)
    {
        $cancellingTagsRs = new TagResourceCollection($this->resource->cancellingTags);
        $cancellingTagsRs->setVisibleFields(
            ['id', 'name', 'text_color', 'background_color', 'sale_probability', 'order']
        );
        $response['cancellingTags'] = $cancellingTagsRs;
        return $response;
    }


    private function loadCancellingStatus($response)
    {
        $cancellingStatusRs = new StatusResourceCollection($this->resource->cancellingStatus);
        $cancellingStatusRs->setVisibleFields(['id', 'name', 'tag_category_id', 'text_color', 'background_color']);
        $response['cancellingStatus'] = $cancellingStatusRs;
        return $response;
    }


    private function loadTriggeringTags($response)
    {

        $triggeringTagsRs = new TagResourceCollection($this->resource->triggeringTags);
        $triggeringTagsRs->setVisibleFields(
            ['id', 'name', 'text_color', 'background_color', 'sale_probability', 'order']
        );
        $response['triggeringTags'] = $triggeringTagsRs;
        return $response;
    }


    private function loadTriggeringStatus($response)
    {
        $triggeringStatusRs = new StatusResourceCollection($this->resource->triggeringStatus);
        $triggeringStatusRs->setVisibleFields(['id', 'name', 'tag_category_id', 'text_color', 'background_color']);
        $response['triggeringStatus'] = $triggeringStatusRs;
        return $response;
    }


    private function loadSendMessageSteps($response)
    {
        if (!$this->resource->relationLoaded('automationEmailSendSteps')) {
            $this->resource->load('automationEmailSendSteps');
        }
        $automationEmailSendStepRs = new AutomationEmailSendStepCollectionResource(
            $this->resource->automationEmailSendSteps
        );
        $response['automationEmailSendSteps'] = $automationEmailSendStepRs;
        return $response;
    }

}
