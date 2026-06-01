<?php

namespace App\Http\Resources\Views\AutomationProposal;

use App\Http\Resources\EmailTemplateResource;
use App\Http\Resources\StatusResourceCollection;
use App\Http\Resources\TagResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;


class AutomationProposalResource extends JsonResource
{

    public function toArray($request)
    {
        $response = [
            'enabled' => $this->resource->enabled ?? null,
            'resendRule' => [
                'enabled' => false,
                'send_hour' => null,
                'cancellingTags' => [],
                'cancellingStatus' => [],
                'send_delay_days' => null,
                'sendEmailTemplate' => null,
                'cancelling_enabled' => false,
                'add_original_attachments' => false,
                'cancel_if_proposal_was_opened' => false,
                'cancel_if_proposal_was_already_sent' => false,
            ],
            'interactionRule' => [
                'addOpenedProposalTag' => false,
                'send_notification_email_to_user' => null,
                'notify_only_if_lead_quality_is_gt' => null,
            ],
            'modifyLeadAfterSendRule' => [
                'addSentProposalTag' => false,
            ],
        ];

        if ($this->resource) {
            $response = $this->loadResendRule($response);
            $response = $this->loadInteractionRule($response);
            $response = $this->loadModifyAfterSendRule($response);
        }
        return $response;
    }


    private function loadResendRule($response)
    {
        if (!$this->resource->relationLoaded('resendRule')) {
            $this->resource->load('resendRule');
        }

        $resendRule = $this->resource->resendRule;

        if ($resendRule) {
            if (!$resendRule->relationLoaded('sendEmailTemplate')) {
                $resendRule->load('sendEmailTemplate');
            }
            $fields = ['id', 'title', 'subject', 'is_proposal', 'is_automation'];
            $sendEmailTemplateRs   = new EmailTemplateResource($resendRule->sendEmailTemplate);
            $sendEmailTemplateRs->setVisibleFields($fields);
            $cancellingTagsRs = new TagResourceCollection($resendRule->cancellingTags);
            $cancellingStatusRs = new StatusResourceCollection($resendRule->cancellingStatusList);

            $resendRuleResponse = [
                'enabled' => $resendRule->enabled,
                'cancellingTags' => $cancellingTagsRs,
                'send_hour' => $resendRule->send_hour,
                'cancellingStatus' => $cancellingStatusRs,
                'sendEmailTemplate' => $sendEmailTemplateRs,
                'send_delay_days' => $resendRule->send_delay_days,
                'cancelling_enabled' => $resendRule->cancelling_enabled,
                'add_original_attachments' => $resendRule->add_original_attachments,
                'cancel_if_proposal_was_opened' => $resendRule->cancel_if_proposal_was_opened,
                'cancel_if_proposal_was_already_sent' => $resendRule->cancel_if_proposal_was_already_sent,
            ];

            $response['resendRule'] = $resendRuleResponse;
        }
        return $response;
    }


    private function loadInteractionRule($response)
    {
        if (!$this->resource->relationLoaded('interactionRule')) {
            $this->resource->load('interactionRule');
        }

        $interactionRule = $this->resource->interactionRule;
        if ($interactionRule) {
            $interactionRuleResponse = [
                'assign_status_id' => $interactionRule->assign_status_id,
                'add_opened_proposal_tag' => $interactionRule->add_opened_proposal_tag,
                'send_notification_email_to_user' => $interactionRule->send_notification_email_to_user,
                'notify_only_if_lead_quality_is_gt' => $interactionRule->notify_only_if_lead_quality_is_gt,
            ];

            $response['interactionRule'] = $interactionRuleResponse;
        }
        return $response;
    }


    private function loadModifyAfterSendRule($response)
    {
        if (!$this->resource->relationLoaded('modifyLeadAfterSendRule')) {
            $this->resource->load('modifyLeadAfterSendRule');
        }
        $modifyLeadAfterSendRule = $this->resource->modifyLeadAfterSendRule;
        if ($modifyLeadAfterSendRule) {
            $modifyLeadAfterSendRuleResponse = [
                'assign_status_id' => $modifyLeadAfterSendRule->assign_status_id,
                'add_sent_proposal_tag' => $modifyLeadAfterSendRule->add_sent_proposal_tag,
            ];

            $response['modifyLeadAfterSendRule'] = $modifyLeadAfterSendRuleResponse;
        }
        return $response;
    }

}
