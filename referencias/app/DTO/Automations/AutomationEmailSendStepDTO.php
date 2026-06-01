<?php

namespace App\DTO\Automations;

use App\Models\Status;
use Illuminate\Support\Collection;


class AutomationEmailSendStepDTO
{

    public $client;
    public $sendHour = null;
    public ?Status $statusToAdd;
    public Collection $tagsToAdd;
    public $sendDelayDays = null;
    public $sendDelayType = null;
    public $sendDelayMinutes = null;
    public $sendEmailTemplate = null;
    public $automationEmailSend = null;


    public static function build($data)
    {
        $dto = new AutomationEmailSendStepDTO($data);
        return $dto;
    }


    public function __construct($data)
    {
        $this->client = $data['client'];
        $this->sendHour = $data['send_hour'] ?? null;
        $this->statusToAdd = $data['statusToAdd'] ?? null;
        $this->tagsToAdd = collect($data['tagsToAdd'] ?? []);
        $this->sendEmailTemplate = $data['sendEmailTemplate'];
        $this->sendDelayDays = $data['send_delay_days'] ?? null;
        $this->automationEmailSend = $data['automationEmailSend'];
        $this->sendDelayMinutes = $data['send_delay_minutes'] ?? null;
    }

}
