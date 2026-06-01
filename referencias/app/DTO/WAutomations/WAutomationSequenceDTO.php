<?php

namespace App\DTO\WAutomations;

use Illuminate\Support\Collection;


class WAutomationSequenceDTO
{

    public $name;
    public $client;
    public $enabled;
    public $triggerType;
    public $triggeringTags;
    public $cancellingTags;
    public $cancellingStatus;
    public $triggeringStatus;
    public $doNotSendWeekends;
    public $cancelIfSequenceWasSent;
    

    public static function build($data)
    {
        $dto = new WAutomationSequenceDTO($data);
        return $dto;
    }


    public function __construct($data)
    {
        $this->client = $data['client'];
        $this->enabled = $data['enabled'] ?? false;
        $this->triggerType = $data['trigger_type'] ?? null;
        $this->name = $data['name'] ?? 'Predefined WAutomation Sequence';
        $this->doNotSendWeekends = $data['do_not_send_weekends'] ?? false;
        $this->triggeringTags = $data['triggeringTags'] ?? new Collection();
        $this->cancellingTags = $data['cancellingTags'] ?? new Collection();
        $this->cancellingStatus = $data['cancellingStatus'] ?? new Collection();
        $this->triggeringStatus = $data['triggeringStatus'] ?? new Collection();
        $this->cancelIfSequenceWasSent = $data['cancel_if_sequence_was_sent'] ?? false;
    }

}
