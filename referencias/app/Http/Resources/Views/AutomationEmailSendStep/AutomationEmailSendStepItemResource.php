<?php

namespace App\Http\Resources\Views\AutomationEmailSendStep;

use App\Http\Resources\EmailTemplateResource;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationEmailSendStepItemResource extends JsonResource
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
            'automation_email_send_id' => $step['automation_email_send_id'],
        ];
        $response = $this->loadTagsToAddField($response);
        $response = $this->loadStatusToAddField($response);
        $response = $this->loadSendEmailTemplate($response);

        return $response;
    }

    private function loadSendEmailTemplate(array $response): array
    {
        if (!$this->resource->relationLoaded('sendEmailTemplate')) {
            $this->resource->load('sendEmailTemplate');
        }
        
        $sendEmailTemplateRs = new EmailTemplateResource($this->resource->sendEmailTemplate);
        $sendEmailTemplateRs->setVisibleFields(['id', 'title', 'subject', 'is_proposal', 'is_automation']);
        $response['sendEmailTemplate'] = $sendEmailTemplateRs;
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
