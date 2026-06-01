<?php

namespace App\Http\Resources\Views\WAutomationLogList;

use App\Http\Resources\WhatsAppTemplateResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WAutomationSequenceStepResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        // this is restricted to prevent data leakage
        $visibleFields = $this->getFieldsToShow();
        if (!$visibleFields) {
            $response = [
                'id' => $this->resource->id,
                'send_hour' => $this->resource->send_hour,
                'created_at' => $this->resource->created_at,
                'send_delay_days' => $this->resource->send_delay_days,
                'send_delay_minutes' => $this->resource->send_delay_minutes,
            ];
        } else {
            $response = $this->resource->attributesToArray();
        }
        $response = $this->loadTagsToAddField($response);
        $response = $this->loadSendWhatsAppTemplate($response);
        $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadSendWhatsAppTemplate(array $response)
    {
        if (!$this->resource->send_whatsapp_template_id) {
            $response['sendWhatsAppTemplate'] = null;
            return $response;
        }

        if (!$this->resource->relationLoaded('sendWhatsAppTemplate')) {
            $this->resource->load([
                'sendWhatsAppTemplate' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new WhatsAppTemplateResource($this->resource->sendWhatsAppTemplate);
        $response['sendWhatsAppTemplate'] = $rs;
        return $response;
    }


    private function loadTagsToAddField(array $response): array
    {
        $response['tagsToAdd'] = $this->resource->tagsToAdd;
        return $response;
    }

}
