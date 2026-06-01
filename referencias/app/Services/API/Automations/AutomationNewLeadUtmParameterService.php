<?php

namespace App\Services\API\Automations;

use App\Models\AutomationNewLead;
use App\DTO\Automations\AutomationNewLeadDTO;
use App\Repositories\Automations\AutomationNewLeadUtmParameterRepository;


class AutomationNewLeadUtmParameterService
{

    public function __construct(
        protected readonly AutomationNewLeadUtmParameterRepository $automationNewLeadUtmParameterRepository
    ) {
    }


    public function create(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto): void
    {
        $this->automationNewLeadUtmParameterRepository->create($automationNewLead, $dto);
    }


    public function deleteAllByAutomation(AutomationNewLead $automationNewLead): void
    {
        $this->automationNewLeadUtmParameterRepository->deleteAllByAutomation($automationNewLead);
    }


    public function deleteAllAndCreate(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto): void
    {
        $this->deleteAllByAutomation($automationNewLead);
        $this->create($automationNewLead, $dto);
    }

}
