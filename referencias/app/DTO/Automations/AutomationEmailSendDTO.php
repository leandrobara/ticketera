<?php

namespace App\DTO\Automations;

use Illuminate\Support\Collection;

class AutomationEmailSendDTO
{
    public $client;

    public $enabled;

    public $name;

    public $cancelIfSequenceWasSent;

    public $doNotSendWeekends;

    public $triggerType;

    public $triggeringStatus;

    public $triggeringTags;

    public $cancellingStatus;

    public $cancellingTags;

    public static function build($data)
    {
        $dto = new AutomationEmailSendDTO($data);

        return $dto;
    }

    public function __construct($data)
    {

        $this->client = $data['client'];
        $this->triggeringStatus = $data['triggeringStatus'] ?? new Collection();
        $this->triggeringTags = $data['triggeringTags'] ?? new Collection();
        $this->cancellingStatus = $data['cancellingStatus'] ?? new Collection();
        $this->cancellingTags = $data['cancellingTags'] ?? new Collection();
        $this->enabled = $data['enabled'] ?? false;
        $this->triggerType = $data['trigger_type'] ?? null;
        $this->name = $data['name'] ?? 'Predefined Automation Email Send';
        $this->cancelIfSequenceWasSent = $data['cancel_if_sequence_was_sent'] ?? false;
        $this->doNotSendWeekends = $data['do_not_send_weekends'] ?? false;
    }
}
