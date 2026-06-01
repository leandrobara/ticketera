<?php

namespace App\Http\Resources\WAutomations;

use App\Http\Resources\ClientResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;

class WAutomationProposalResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('resendRule', $visibleFields)) {
            $response = $this->loadResendRule($response);
        }
        if (in_array('modifyLeadAfterSendRule', $visibleFields)) {
            $response = $this->loadModifyLeadAfterSendRule($response);
        }
        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        $response = $this->filterVisibleFields($response);
        return $response;
    }


    public function loadResendRule(array $response)
    {
        if (!$this->resource->relationLoaded('resendRule')) {
            $this->resource->load('resendRule');
        }

        $resendRule = $this->resource->resendRule;
        if ($resendRule) {
            $resendRuleRs = new WAutomationProposalResendResource($resendRule);
            $resendRuleRs->setVisibleFields([
                'id',
                'enabled',
                'send_hour',
                'cancellingTags',
                'send_delay_days',
                'cancellingStatus',
                'cancelling_enabled',
                'sendWhatsAppTemplate',
                'do_not_send_weekends',
                'wautomation_proposal_id',
                'add_original_attachments',
                'cancel_if_proposal_was_already_sent',
            ]);
            $response['resendRule'] = $resendRuleRs;
        }

        return $response;
    }


    public function loadModifyLeadAfterSendRule(array $response)
    {
        if (!$this->resource->relationLoaded('modifyLeadAfterSendRule')) {
            $this->resource->load('modifyLeadAfterSendRule');
        }

        $modifyLeadAfterSendRule = $this->resource->modifyLeadAfterSendRule;

        if ($modifyLeadAfterSendRule) {
            $modifyLeadAfterSendRuleRs = new WAutomationProposalModifyLeadAfterSendResource($modifyLeadAfterSendRule);
            $modifyLeadAfterSendRuleRs->setVisibleFields(['add_sent_proposal_tag', 'assign_status_id']);
            $response['modifyLeadAfterSendRule'] = $modifyLeadAfterSendRuleRs;
        }
        return $response;
    }


    private function loadClientField(array $response): array
    {
        if (!$this->resource->relationLoaded('client')) {
            $this->resource->load('client');
        }
        $visibleFields = ['id', 'name', 'subdomain', 'country_code', 'version'];
        $clientRs = new ClientResource($this->resource->client);
        $clientRs->setVisibleFields($visibleFields);
        $response['client'] = $clientRs;
        return $response;
    }

}
