<?php

namespace App\Http\Resources\Views\WAutomationSequence;

use App\Http\Resources\TagResourceCollection;
use App\Http\Resources\StatusResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Views\WAutomationSequenceStep\WAutomationSequenceStepCollectionResource;


class WAutomationSequenceItemResource extends JsonResource
{

    public function toArray($request)
    {
        $wAutomation = $this->resource->toArray();
        $wasApplied = $this->resource?->wAutomationLog ? true : false;

        $response = [
            'id' => $wAutomation['id'],
            'was_applied' => $wasApplied,
            'name' => $wAutomation['name'],
            'enabled' => $wAutomation['enabled'],
            'updated_at' => $wAutomation['updated_at'],
            'trigger_type' => $wAutomation['trigger_type'],
            'do_not_send_weekends' => $wAutomation['do_not_send_weekends'],
            'cancel_if_sequence_was_sent' => $wAutomation['cancel_if_sequence_was_sent'],
        ];
        $response = $this->loadSequenceSteps($response);
        $response = $this->loadCancellingTags($response);
        $response = $this->loadCancellingStatus($response);
        $response = $this->loadTriggeringTags($response);
        $response = $this->loadTriggeringStatus($response);
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


    private function loadSequenceSteps($response)
    {
        if (!$this->resource->relationLoaded('wAutomationSequenceSteps')) {
            $this->resource->load('wAutomationSequenceSteps');
        }
        $wAutomationSequenceStepRs = new WAutomationSequenceStepCollectionResource(
            $this->resource->wAutomationSequenceSteps
        );
        $response['wAutomationSequenceSteps'] = $wAutomationSequenceStepRs;
        return $response;
    }

}
