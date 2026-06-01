<?php

namespace App\DTO\Automations\Parameters;

use App\DTO\Automations\AutomationNewLeadDTO;

class ListAutomationNewLeadDTO
{
    public $client;


    public static function build(array $data = []): AutomationNewLeadDTO
    {
        $dto = new AutomationNewLeadDTO($data);
        return $dto;
    }

    public function __construct($data = [])
    {
        $this->client = $data['client'] ?? null;
    }
}
