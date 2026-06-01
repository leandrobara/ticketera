<?php

namespace App\Services\API\Automations;

use App\Models\AutomationNewLead;
use App\DTO\Automations\AutomationNewLeadDTO;
use App\Repositories\Automations\AutomationNewLeadTrackingParameterRepository;


class AutomationNewLeadTrackingParameterService
{

    public function __construct(
        private AutomationNewLeadTrackingParameterRepository $automationNewLeadTrackingParameterRepository
    ) {
    }


    public function create(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto)
    {
        $this->automationNewLeadTrackingParameterRepository->create($automationNewLead, $dto);
    }


    public function deleteAllByAutomation(AutomationNewLead $automationNewLead)
    {
        $this->automationNewLeadTrackingParameterRepository->deleteAllByAutomation($automationNewLead);
    }


    public function deleteAllAndCreate(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto)
    {
        $this->deleteAllByAutomation($automationNewLead);
        return $this->create($automationNewLead, $dto);
    }

}
