<?php

namespace App\DTO\WAutomations;


class WAutomationProposalDTO
{

    public $id = null;
    public $client = null;
    public $enabled = false;
    public $wAutomationProposal = null;
    public $wAutomationProposalResendDTO = null;
    public $wAutomationProposalModifyLeadAfterSendDTO = null;


    public static function build($data)
    {
        $dto = new WAutomationProposalDTO($data);
        return $dto;
    }


    public function __construct($data)
    {
        $this->enabled = $data['enabled'];

        if ($data['resendRule'] ?? null) {
            $this->wAutomationProposalResendDTO = new WAutomationProposalResendDTO($data['resendRule']);
        }

        if ($data['modifyLeadAfterSendRule'] ?? null) {
            $this->wAutomationProposalModifyLeadAfterSendDTO = new WAutomationPropoposalModifyLeadAfterSendDTO(
                $data['modifyLeadAfterSendRule']
            );
        }
    }

}
