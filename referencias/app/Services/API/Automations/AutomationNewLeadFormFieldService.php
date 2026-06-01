<?php

namespace App\Services\API\Automations;

use App\Models\AutomationNewLead;
use App\DTO\Automations\AutomationNewLeadDTO;
use App\Repositories\Automations\AutomationNewLeadFormFieldRepository;


class AutomationNewLeadFormFieldService
{

    public function __construct(AutomationNewLeadFormFieldRepository $automationNewLeadFormFieldRepository)
    {
        $this->automationNewLeadFormFieldRepository = $automationNewLeadFormFieldRepository;
    }


    public function create(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto)
    {
        $this->automationNewLeadFormFieldRepository->create($automationNewLead, $dto);
    }


    public function deleteAllByAutomation(AutomationNewLead $automationNewLead)
    {
        $this->automationNewLeadFormFieldRepository->deleteAllByAutomation($automationNewLead);
    }


    public function deleteAllAndCreate(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto)
    {
        $this->deleteAllByAutomation($automationNewLead);
        return $this->create($automationNewLead, $dto);
    }

}
