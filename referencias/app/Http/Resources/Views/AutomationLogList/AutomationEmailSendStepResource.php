<?php

namespace App\Http\Resources\Views\AutomationLogList;

use App\Http\Resources\EmailTemplateResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class AutomationEmailSendStepResource extends JsonResource
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
        $response = $this->loadSendEmailTemplate($response);
        $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadSendEmailTemplate(array $response)
    {
        if (!$this->resource->send_email_template_id) {
            $response['sendEmailTemplate'] = null;
            return $response;
        }

        if (!$this->resource->relationLoaded('sendEmailTemplate')) {
            $this->resource->load([
                'sendEmailTemplate' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new EmailTemplateResource($this->resource->sendEmailTemplate);
        $response['sendEmailTemplate'] = $rs;
        return $response;
    }


    private function loadTagsToAddField(array $response): array
    {
        $response['tagsToAdd'] = $this->resource->tagsToAdd;
        return $response;
    }

}
