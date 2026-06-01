<?php

namespace App\DTO\WAutomations;

use App\Models\Status;
use Illuminate\Support\Collection;


class WAutomationSequenceStepDTO
{

    public $client;
    public $sendHour = null;
    public ?Status $statusToAdd;
    public Collection $tagsToAdd;
    public $sendDelayDays = null;
    public $sendDelayType = null;
    public $sendDelayMinutes = null;
    public $wAutomationSequence = null;
    public $sendWhatsAppTemplate = null;


    public static function build($data)
    {
        $dto = new WAutomationSequenceStepDTO($data);
        return $dto;
    }


    public function __construct($data)
    {
        $this->client = $data['client'];
        $this->sendHour = $data['send_hour'] ?? null;
        $this->statusToAdd = $data['statusToAdd'] ?? null;
        $this->tagsToAdd = collect($data['tagsToAdd'] ?? []);
        $this->sendDelayDays = $data['send_delay_days'] ?? null;
        $this->wAutomationSequence = $data['wAutomationSequence'];
        $this->sendWhatsAppTemplate = $data['sendWhatsAppTemplate'];
        $this->sendDelayMinutes = $data['send_delay_minutes'] ?? null;
    }

}
