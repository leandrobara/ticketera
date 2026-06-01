<?php

namespace App\Services\API\Automations;

use App\Models\AutomationNewLead;
use App\DTO\Automations\AutomationNewLeadDTO;
use App\Repositories\Automations\AutomationNewLeadCustomFieldMatchRepository;


class AutomationNewLeadCustomFieldMatchService
{

    public function __construct(
        private AutomationNewLeadCustomFieldMatchRepository $automationNewLeadCustomFieldMatchRepository
    ) {
    }


    public function create(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto): void
    {
        $this->automationNewLeadCustomFieldMatchRepository->create($automationNewLead, $dto);
    }


    public function deleteAllByAutomation(AutomationNewLead $automationNewLead): void
    {
        $this->automationNewLeadCustomFieldMatchRepository->deleteAllByAutomation($automationNewLead);
    }


    public function deleteAllAndCreate(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto): void
    {
        $this->deleteAllByAutomation($automationNewLead);
        $this->create($automationNewLead, $dto);
    }
}


