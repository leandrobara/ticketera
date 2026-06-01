<?php

namespace App\Http\Resources\Views\AutomationLogList;

use App\Http\Resources\LeadResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\Http\Resources\Automations\AutomationTaskResource;


class AutomationLogListItemResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $automationLog = $this->resource->toArray();
        $response = [
            'id' => $automationLog['id'],
            'lead_id' => $automationLog['lead_id'],
            'email_id' => $automationLog['email_id'],
            'exception' => $automationLog['exception'],
            'client_id' => $automationLog['client_id'],
            'created_at' => $automationLog['created_at'],
            'is_fully_applied' => $automationLog['is_fully_applied'],
            'automation_proposal_interaction_rule_id' => $automationLog['automation_proposal_interaction_rule_id'],
            'automation_proposal_modify_lead_after_send_rule_id' => $automationLog[
                'automation_proposal_modify_lead_after_send_rule_id'
            ],
        ];

        $response = $this->loadLead($response);
        $response = $this->loadAutomationTask($response);
        $response = $this->loadAutomationNewLead($response);
        $response = $this->loadAutomationProposal($response);
        $response = $this->loadAutomationEmailSend($response);
        $response = $this->loadAutomationEmailSendStep($response);
        $response = $this->loadAutomationNewLeadAssignedUser($response);
        $response = $this->loadAutomationProposalResendRule($response);
        $response = $this->loadAutomationProposalInteractionRule($response);
        $response = $this->loadAutomationsProposalModifyLeadAfterSendRule($response);

        return $response;
    }


    private function loadAutomationTask(array $response)
    {
        if (!$this->resource->automationTask) {
            $response['automationTask'] = null;
            return $response;
        }
        if (!$this->resource->relationLoaded('automationTask')) {
            $this->resource->load([
                'automationTask' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }

        $rs = new AutomationTaskResource($this->resource->automationTask);
        $rs->setVisibleFields([
            'id',
            'create_hour',
            'is_recurrent',
            'trigger_type',
            'allowingTags',
            'tagsToAssign',
            'taskTemplate',
            'triggeringTags',
            'cancellingTags',
            'allowingStatus',
            'statusToAssign',
            'triggeringStatus',
            'cancellingStatus',
            'create_delay_days',
            'is_immediately_created',
        ]);
        $response['automationTask'] = $rs;
        return $response;
    }


    private function loadAutomationsProposalModifyLeadAfterSendRule(array $response)
    {
        if (!$this->resource->automation_proposal_modify_lead_after_send_rule_id) {
            $response['automationProposalModifyLeadAfterSendRule'] = null;
            return $response;
        }
        if (!$this->resource->automationProposal->relationLoaded('modifyLeadAfterSendRule')) {
            $this->resource->automationProposal->load([
                'modifyLeadAfterSendRule' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new AutomationProposalModifyLeadAfterSendRuleResource(
            $this->resource->automationProposal->modifyLeadAfterSendRule
        );
        $response['automationProposalModifyLeadAfterSendRule'] = $rs;
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


    private function loadAutomationNewLead(array $response)
    {
        if (!$this->resource->automation_new_lead_id) {
            $response['automationNewLead'] = null;
            return $response;
        }
        if (!$this->resource->relationLoaded('automationNewLead')) {
            $this->resource->load([
                'automationNewLead' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new AutomationNewLeadResource($this->resource->automationNewLead);
        $response['automationNewLead'] = $rs;
        return $response;
    }


    private function loadAutomationNewLeadAssignedUser(array $response)
    {
        if (!$this->resource->automation_new_lead_assigned_user_id) {
            $response['automationNewLeadAssignedUser'] = null;
            return $response;
        }
        if (!$this->resource->relationLoaded('automationNewLeadAssignedUser')) {
            $this->resource->load([
                'automationNewLeadAssignedUser' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $visibleFields = ['id', 'type', 'name', 'last_name', 'phone', 'email'];
        $rs = new UserResource($this->resource->automationNewLeadAssignedUser);
        $rs->setVisibleFields($visibleFields);
        $response['automationNewLeadAssignedUser'] = $rs;
        return $response;
    }


    private function loadAutomationEmailSend(array $response)
    {
        if (!$this->resource->automation_email_send_id) {
            $response['automationEmailSend'] = null;
            return $response;
        }
        if (!$this->resource->relationLoaded('automationEmailSend')) {
            $this->resource->load([
                'automationEmailSend' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new AutomationEmailSendResource($this->resource->automationEmailSend);
        $response['automationEmailSend'] = $rs;
        return $response;
    }


    private function loadAutomationEmailSendStep(array $response)
    {
        if (!$this->resource->automation_email_send_step_id) {
            $response['automationEmailSendStep'] = null;
            return $response;
        }
        if (!$this->resource->relationLoaded('automationEmailSendStep')) {
            $this->resource->load([
                'automationEmailSendStep' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        if (!$this->resource->relationLoaded('automationEmailSendStep')) {
            $this->resource->load([
                'automationEmailSendStep' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new AutomationEmailSendStepResource($this->resource->automationEmailSendStep);
        $response['automationEmailSendStep'] = $rs;
        return $response;
    }


    private function loadAutomationProposal(array $response)
    {
        if (!$this->resource->automation_proposal_id) {
            $response['automationProposal'] = null;
            return $response;
        }
        if (!$this->resource->relationLoaded('automationProposal')) {
            $this->resource->load([
                'automationProposal' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new AutomationProposalResource($this->resource->automationProposal);
        $response['automationProposal'] = $rs;
        return $response;
    }


    private function loadAutomationProposalResendRule(array $response)
    {
        if (!$this->resource->automation_proposal_resend_rule_id) {
            $response['automationProposalResendRule'] = null;
            return $response;
        }
        if (!$this->resource->automationProposal->relationLoaded('resendRule')) {
            $this->resource->automationProposal->load([
                'resendRule' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new AutomationProposalResendRuleResource($this->resource->automationProposal->resendRule);
        $response['automationProposalResendRule'] = $rs;
        return $response;
    }


    private function loadAutomationProposalInteractionRule(array $response)
    {
        if (!$this->resource->automation_proposal_interaction_rule_id) {
            $response['automationProposalInteractionRule'] = null;
            return $response;
        }
        if (!$this->resource->automationProposal->relationLoaded('interactionRule')) {
            $this->resource->automationProposal->load([
                'interactionRule' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $rs = new AutomationProposalInteractionRuleResource($this->resource->automationProposal->interactionRule);
        $response['automationProposalInteractionRule'] = $rs;
        return $response;
    }

}
