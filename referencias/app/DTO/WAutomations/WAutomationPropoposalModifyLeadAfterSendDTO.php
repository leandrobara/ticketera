<?php

namespace App\DTO\WAutomations;

use Illuminate\Support\Collection;


class WAutomationPropoposalModifyLeadAfterSendDTO
{

    public $addTags;
    public $removeTags;
    public $assignStatus;
    public $addSentProposalTag;


    public static function build($data)
    {
        $dto = new WAutomationPropoposalModifyLeadAfterSendDTO($data);
        return $dto;
    }


    public function __construct($data)
    {
        $this->assignStatus = $data['assignStatus'] ?? null;
        $this->addTags = $data['addTags'] ?? new Collection();
        $this->removeTags = $data['removeTags'] ?? new Collection();
        $this->addSentProposalTag = $data['add_sent_proposal_tag'] ?? false;
    }

}
