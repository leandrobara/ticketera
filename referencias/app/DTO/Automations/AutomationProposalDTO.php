<?php

namespace App\DTO\Automations;


class AutomationProposalDTO
{

    public $id = null;
    public $client = null;
    public $enabled = false;
    public $automationProposal = null;
    public $automationProposalResendDTO = null;
    public $automationProposalInteractionDTO = null;
    public $automationProposalModifyLeadAfterSendDTO = null;


    public static function build($data)
    {
        $dto = new AutomationProposalDTO($data);
        return $dto;
    }


    public function __construct($data)
    {
        $this->enabled = $data['enabled'];

        if ($data['resendRule'] ?? null) {
            $this->automationProposalResendDTO = new AutomationProposalResendDTO($data['resendRule']);
        }

        if ($data['interactionRule'] ?? null) {
            $this->automationProposalInteractionDTO = new AutomationProposalInteractionDTO($data['interactionRule']);
        }

        if ($data['modifyLeadAfterSendRule'] ?? null) {
            $this->automationProposalModifyLeadAfterSendDTO = new AutomationPropoposalModifyLeadAfterSendDTO(
                $data['modifyLeadAfterSendRule']
            );
        }
    }

}
