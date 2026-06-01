<?php

namespace App\DTO\Automations;

use Illuminate\Support\Collection;


class AutomationTaskDTO
{

    public $client;
    public $enabled;
    public $isRecurrent;
    public $triggerType;
    public $tagsToAssign;
    public $allowingTags;
    public $taskTemplateId;
    public $statusToAssign;
    public $allowingStatus;
    public $triggeringTags;
    public $cancellingTags;
    public $createDelayHour;
    public $createDelayDays;
    public $triggeringStatus;
    public $cancellingStatus;
    public $isImmediateCreate;


    public static function build($data)
    {
        $dto = new AutomationTaskDTO($data);
        return $dto;
    }


    public function __construct($data)
    {
        $this->client = $data['client'];
        $this->enabled = $data['enabled'] ?? false;
        $this->createHour = $data['create_hour'] ?? null;
        $this->triggerType = $data['trigger_type'] ?? null;
        $this->isRecurrent = $data['is_recurrent'] ?? false;
        $this->statusToAssign = $data['statusToAssign'] ?? null;
        $this->taskTemplateId = $data['task_template_id'] ?? null;
        $this->createDelayDays = $data['create_delay_days'] ?? null;
        $this->tagsToAssign = $data['tagsToAssign'] ?? new Collection();
        $this->allowingTags = $data['allowingTags'] ?? new Collection();
        $this->triggeringTags = $data['triggeringTags'] ?? new Collection();
        $this->cancellingTags = $data['cancellingTags'] ?? new Collection();
        $this->allowingStatus = $data['allowingStatus'] ?? new Collection();
        $this->isImmediatelyCreated = $data['is_immediately_created'] ?? false;
        $this->triggeringStatus = $data['triggeringStatus'] ?? new Collection();
        $this->cancellingStatus = $data['cancellingStatus'] ?? new Collection();
    }

}
