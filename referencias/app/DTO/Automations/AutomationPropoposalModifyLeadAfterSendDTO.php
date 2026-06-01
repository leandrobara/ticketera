<?php

namespace App\DTO\Automations;

use Illuminate\Support\Collection;

class AutomationPropoposalModifyLeadAfterSendDTO
{
    public $client;

    public $automationProposal;

    public $addTags;

    public $removeTags;

    public $assignStatus;

    public $addSentProposalTag;

    public static function build($data)
    {
        $dto = new AutomationPropoposalModifyLeadAfterSendDTO($data);

        return $dto;
    }

    /**
     * @param $data
     */
    public function __construct($data)
    {
        $this->addTags = $data['addTags'] ?? new Collection();
        $this->removeTags = $data['removeTags'] ?? new Collection();
        $this->assignStatus = $data['assignStatus'] ?? null;
        $this->addSentProposalTag = $data['add_sent_proposal_tag'] ?? false;
    }
}
