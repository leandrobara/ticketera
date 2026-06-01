<?php

namespace App\Services\API\Automations;

use App\Models\AutomationNewLead;
use App\DTO\Automations\AutomationNewLeadDTO;
use App\Repositories\Automations\AutomationNewLeadCustomFieldMappingRepository;


class AutomationNewLeadCustomFieldMappingService
{

    public function __construct(AutomationNewLeadCustomFieldMappingRepository $repo)
    {
        $this->automationNewLeadCustomFieldMappingRepository = $repo;
    }


    public function create(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto)
    {
        $this->automationNewLeadCustomFieldMappingRepository->create($automationNewLead, $dto);
    }


    public function deleteAllByAutomation(AutomationNewLead $automationNewLead)
    {
        $this->automationNewLeadCustomFieldMappingRepository->deleteAllByAutomation($automationNewLead);
    }


    public function deleteAllAndCreate(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto)
    {
        $this->deleteAllByAutomation($automationNewLead);
        return $this->create($automationNewLead, $dto);
    }

}
