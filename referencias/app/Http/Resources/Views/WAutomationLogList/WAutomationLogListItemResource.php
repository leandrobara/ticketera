<?php

namespace App\Http\Resources\Views\WAutomationLogList;

use App\Http\Resources\LeadResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\Http\Resources\Views\WhatsAppSending\WhatsAppSendingResource;
use App\Http\Resources\Views\WhatsAppSending\WhatsAppSendingMessageResource;


class WAutomationLogListItemResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $wAutomationLog = $this->resource->toArray();
        $response = [
            'id' => $wAutomationLog['id'],
            'lead_id' => $wAutomationLog['lead_id'],
            'exception' => $wAutomationLog['exception'],
            'client_id' => $wAutomationLog['client_id'],
            'exception' => $wAutomationLog['exception'],
            'created_at' => $wAutomationLog['created_at'],
            'is_fully_applied' => $wAutomationLog['is_fully_applied'],
            'whatsapp_sending_id' => $wAutomationLog['whatsapp_sending_id'],
            'wautomation_after_send_id' => $wAutomationLog['wautomation_after_send_id'],
            'wautomation_proposal_resend_rule_id' => $wAutomationLog['wautomation_proposal_resend_rule_id'],
            'wautomation_proposal_modify_lead_after_send_rule_id' => $wAutomationLog[
                'wautomation_proposal_modify_lead_after_send_rule_id'
            ],
        ];

        $response = $this->loadLead($response);
        $response = $this->loadWAutomationProposal($response);
        $response = $this->loadWAutomationAfterSend($response);
        $response = $this->loadWAutomationSequence($response);
        $response = $this->loadWAutomationSequenceStep($response);
        $response = $this->loadWAutomationProposalResendRule($response);
        $response = $this->loadWAutomationsProposalModifyLeadAfterSendRule($response);
        
        $response = $this->loadTriggeringWhatsAppSending($response);
        $response = $this->loadTriggeringWhatsAppSendingMessage($response);
        
        $response = $this->loadSentWhatsAppSendingMessage($response);

        return $response;
    }


    private function loadLead(array $response)
    {
        if (!$this->resource->relationLoaded('lead')) {
            $this->resource->load([
                'lead' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $visibleFields = [
            'id',
            'user',
            'method',
            'company',
            'mainLeadContact',
            'is_bulk_created',
            'is_facebook_form',
            'is_whatsapp_form',
            'is_manually_created',
        ];
        $rs = new LeadResource($this->resource->lead);
        $rs->setVisibleFields($visibleFields);
        $response['lead'] = $rs;
        return $response;
    }


    private function loadTriggeringWhatsAppSending(array $response)
    {
        if (!$this->resource->relationLoaded('whatsAppSending')) {
            $this->resource->load([
                'whatsAppSending' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new WhatsAppSendingResource($this->resource->whatsAppSending);
        $rs->opts['loadWapSendingMsgs'] = false;
        $response['triggeringWhatsAppSending'] = $rs;
        return $response;
    }


    private function loadTriggeringWhatsAppSendingMessage(array $response)
    {
        if (!$this->resource->relationLoaded('whatsAppSendingMessage')) {
            $this->resource->load([
                'whatsAppSendingMessage' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new WhatsAppSendingMessageResource($this->resource->whatsAppSendingMessage);
        $response['triggeringWhatsAppSendingMessage'] = $rs;
        return $response;
    }


    private function loadSentWhatsAppSendingMessage(array $response)
    {
        if (!$this->resource->relationLoaded('sentWhatsAppSendingMessage')) {
            $this->resource->load('sentWhatsAppSendingMessage');
        }
        $rs = new WhatsAppSendingMessageResource($this->resource->sentWhatsAppSendingMessage);
        $response['sentWhatsAppSendingMessage'] = $rs;
        return $response;
    }


    private function loadWAutomationSequence(array $response)
    {
        if (!$this->resource->wautomation_sequence_id) {
            $response['wAutomationSequence'] = null;
            return $response;
        }
        if (!$this->resource->relationLoaded('wAutomationSequence')) {
            $this->resource->load([
                'wAutomationSequence' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new WAutomationSequenceResource($this->resource->wAutomationSequence);
        $response['wAutomationSequence'] = $rs;
        return $response;
    }


    private function loadWAutomationSequenceStep(array $response)
    {
        if (!$this->resource->wautomation_sequence_step_id) {
            $response['wAutomationSequenceStep'] = null;
            return $response;
        }
        if (!$this->resource->relationLoaded('wAutomationSequenceStep')) {
            $this->resource->load([
                'wAutomationSequenceStep' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        if (!$this->resource->relationLoaded('wAutomationSequenceStep')) {
            $this->resource->load([
                'wAutomationSequenceStep' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new WAutomationSequenceStepResource($this->resource->wAutomationSequenceStep);
        $response['wAutomationSequenceStep'] = $rs;
        return $response;
    }


    private function loadWAutomationAfterSend(array $response)
    {
        if (!$this->resource->wautomation_after_send_id) {
            $response['wAutomationAfterSend'] = null;
            return $response;
        }
        if (!$this->resource->relationLoaded('wAutomationAfterSend')) {
            $this->resource->load([
                'wAutomationAfterSend' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new WAutomationAfterSendResource($this->resource->wAutomationAfterSend);
        $response['wAutomationAfterSend'] = $rs;
        return $response;
    }


    private function loadWAutomationProposal(array $response)
    {
        if (!$this->resource->wautomation_proposal_id) {
            $response['wAutomationProposal'] = null;
            return $response;
        }
        if (!$this->resource->relationLoaded('wAutomationProposal')) {
            $this->resource->load([
                'wAutomationProposal' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new WAutomationProposalResource($this->resource->wAutomationProposal);
        $response['wAutomationProposal'] = $rs;
        return $response;
    }


    private function loadWAutomationProposalResendRule(array $response)
    {
        if (!$this->resource->wautomation_proposal_resend_rule_id) {
            $response['wAutomationProposalResendRule'] = null;
            return $response;
        }
        if (!$this->resource->relationLoaded('wAutomationProposalResendRule')) {
            $this->resource->load([
                'wAutomationProposalResendRule' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new WAutomationProposalResendRuleResource($this->resource->wAutomationProposalResendRule);
        $response['wAutomationProposalResendRule'] = $rs;
        return $response;
    }


    private function loadWAutomationsProposalModifyLeadAfterSendRule(array $response)
    {
        if (!$this->resource->wautomation_proposal_modify_lead_after_send_rule_id) {
            $response['wAutomationProposalModifyLeadAfterSendRule'] = null;
            return $response;
        }
        if (!$this->resource->relationLoaded('wAutomationProposalModifyLeadAfterSendRule')) {
            $this->resource->load([
                'wAutomationProposalModifyLeadAfterSendRule' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new WAutomationProposalModifyLeadAfterSendRuleResource(
            $this->resource->wAutomationProposalModifyLeadAfterSendRule
        );
        $response['wAutomationProposalModifyLeadAfterSendRule'] = $rs;
        return $response;
    }

}
