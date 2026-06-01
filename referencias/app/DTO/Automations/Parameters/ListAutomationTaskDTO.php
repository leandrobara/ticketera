<?php

namespace App\DTO\Automations\Parameters;

use App\DTO\Automations\AutomationTaskDTO;

class ListAutomationTaskDTO
{
    public $client;


    public static function build(array $data = []): AutomationTaskDTO
    {
        $dto = new AutomationTaskDTO($data);
        return $dto;
    }

    public function __construct($data = [])
    {
        $this->client = $data['client'] ?? null;
    }
}
