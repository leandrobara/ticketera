<?php

namespace App\Http\Resources\Views\WAutomationLogList;

use App\Http\Resources\WhatsAppTemplateResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WAutomationProposalResendRuleResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        // this is restricted to prevent data leakage
        $visibleFields = $this->getFieldsToShow();
        if (!$visibleFields) {
            $response = [
                'id' => $this->resource->id,
                'send_hour' => $this->send_hour,
                'created_at' => $this->created_at,
                'send_delay_days' => $this->send_delay_days,
                'cancelling_enabled' => $this->cancelling_enabled,
                'do_not_send_weekends' => $this->do_not_send_weekends,
                'add_original_attachments' => $this->add_original_attachments,
            ];
        } else {
            $response = $this->resource->attributesToArray();
        }

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

}
