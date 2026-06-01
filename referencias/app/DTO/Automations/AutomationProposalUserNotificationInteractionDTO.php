<?php

namespace App\DTO\Automations;

use Illuminate\Support\Collection;

class AutomationProposalUserNotificationInteractionDTO
{
    public $client;

    public $automationProposal;

    public $cancellingTags;

    public $cancellingStatus;

    public $leadQualities;

    public static function build($data)
    {
        $dto = new AutomationProposalUserNotificationInteractionDTO($data);

        return $dto;
    }

    /**
     * @param $data
     */
    public function __construct($data)
    {
        $this->triggerType = $data['trigger_type'];
        $this->cancellingTags = $data['cancellingTags'] ?? new Collection();
        $this->cancellingStatus = $data['cancellingStatus'] ?? new Collection();
        $this->leadQualities = $data['lead_qualities'] ?? new Collection();
    }
}
