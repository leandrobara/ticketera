<?php

namespace App\Http\Resources\Views\WAutomationSequenceStep;

use App\Http\Resources\WhatsAppTemplateResource;
use Illuminate\Http\Resources\Json\JsonResource;


class WAutomationSequenceStepItemResource extends JsonResource
{

    public function toArray($request)
    {
        $step = $this->resource->toArray();
        $response = [
            'id' => $step['id'],
            'send_hour' => $step['send_hour'],
            'updated_at' => $step['updated_at'],
            'send_delay_days' => $step['send_delay_days'],
            'send_delay_minutes' => $step['send_delay_minutes'],
            'wautomation_sequence_id' => $step['wautomation_sequence_id'],
        ];
        $response = $this->loadTagsToAddField($response);
        $response = $this->loadStatusToAddField($response);
        $response = $this->loadSendWhatsAppTemplate($response);
        return $response;
    }


    private function loadSendWhatsAppTemplate(array $response): array
    {
        if (!$this->resource->relationLoaded('sendWhatsAppTemplate')) {
            $this->resource->load('sendWhatsAppTemplate');
        }
        $sendWhatsAppTemplateRs = new WhatsAppTemplateResource($this->resource->sendWhatsAppTemplate);
        $sendWhatsAppTemplateRs->setVisibleFields(['id', 'title', 'subject', 'is_proposal', 'is_automation']);
        $response['sendWhatsAppTemplate'] = $sendWhatsAppTemplateRs;
        return $response;
    }


    private function loadTagsToAddField(array $response): array
    {
        $response['tagsToAdd'] = $this->resource->tagsToAdd;
        return $response;
    }


    private function loadStatusToAddField(array $response): array
    {
        $response['statusToAdd'] = $this->resource->statusToAdd;
        return $response;
    }

}
