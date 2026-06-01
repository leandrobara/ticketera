<?php

namespace App\Http\Resources\Views\AutomationLogList;

use App\Http\Resources\EmailTemplateResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class AutomationProposalResendRuleResource extends JsonResource
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
                'add_original_attachments' => $this->add_original_attachments,
                'cancel_if_proposal_was_opened' => $this->cancel_if_proposal_was_opened,
                'cancel_if_proposal_was_already_sent' => $this->cancel_if_proposal_was_already_sent,
            ];
        } else {
            $response = $this->resource->attributesToArray();
        }

        // $response = $this->loadCancellingTags($response);
        // $response = $this->loadCancellingStatus($response);
        $response = $this->loadSendEmailTemplate($response);

        $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadCancellingTags(array $response)
    {
        $cancellingTags = $this->resource->cancellingTags;
        if (empty($cancellingTags)) {
            $response['cancellingTags'] = null;
            return $response;
        }
        $response['cancellingTags'] = $cancellingTags;
        return $response;
    }


    private function loadCancellingStatus(array $response)
    {
        $cancellingStatus = $this->resource->cancellingStatusList;
        if (empty($cancellingStatus)) {
            $response['cancellingStatus'] = null;
            return $response;
        }
        $response['cancellingStatus'] = $cancellingStatus;
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

}
