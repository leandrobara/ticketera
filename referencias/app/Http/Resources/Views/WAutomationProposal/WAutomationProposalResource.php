<?php

namespace App\Http\Resources\Views\WAutomationProposal;

use App\Http\Resources\TagResourceCollection;
use App\Http\Resources\WhatsAppTemplateResource;
use App\Http\Resources\StatusResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;


class WAutomationProposalResource extends JsonResource
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
                'cancelling_enabled' => false,
                'sendWhatsAppTemplate' => null,
                'do_not_send_weekends' => false,
                'add_original_attachments' => false,
                'cancel_if_proposal_was_already_sent' => false,
            ],
            'modifyLeadAfterSendRule' => [
                'addSentProposalTag' => false,
            ],
        ];

        if ($this->resource) {
            $response = $this->loadResendRule($response);
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
            if (!$resendRule->relationLoaded('sendWhatsAppTemplate')) {
                $resendRule->load('sendWhatsAppTemplate');
            }
            $fields = ['id', 'title', 'subject', 'is_proposal', 'is_automation'];
            $sendWhatsAppTemplateRs   = new WhatsAppTemplateResource($resendRule->sendWhatsAppTemplate);
            $sendWhatsAppTemplateRs->setVisibleFields($fields);
            $cancellingTagsRs = new TagResourceCollection($resendRule->cancellingTags);
            $cancellingStatusRs = new StatusResourceCollection($resendRule->cancellingStatusList);

            $resendRuleResponse = [
                'enabled' => $resendRule->enabled,
                'cancellingTags' => $cancellingTagsRs,
                'send_hour' => $resendRule->send_hour,
                'cancellingStatus' => $cancellingStatusRs,
                'send_delay_days' => $resendRule->send_delay_days,
                'sendWhatsAppTemplate' => $sendWhatsAppTemplateRs,
                'cancelling_enabled' => $resendRule->cancelling_enabled,
                'do_not_send_weekends' => $resendRule->do_not_send_weekends,
                'add_original_attachments' => $resendRule->add_original_attachments,
                'cancel_if_proposal_was_already_sent' => $resendRule->cancel_if_proposal_was_already_sent,
            ];

            $response['resendRule'] = $resendRuleResponse;
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
