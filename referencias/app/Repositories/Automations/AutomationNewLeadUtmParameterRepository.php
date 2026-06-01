<?php

namespace App\Repositories\Automations;

use Exception;
use Throwable;
use App\Models\AutomationNewLead;
use Illuminate\Support\Facades\DB;
use App\Exceptions\DatabaseException;
use App\DTO\Automations\AutomationNewLeadDTO;
use App\Models\AutomationNewLeadUtmParameter;


class AutomationNewLeadUtmParameterRepository
{

    public function create(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto): void
    {
        try {
            DB::beginTransaction();
            foreach ($dto->utmParameters as $data) {
                $data['client_id'] = $automationNewLead->client_id;
                $data['automation_new_lead_id'] = $automationNewLead->id;
                $field = new AutomationNewLeadUtmParameter($data);
                $field->save();
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }


    public function deleteAllByAutomation(AutomationNewLead $automationNewLead): void
    {
        $automationNewLead->utmParametersToMatch()->delete();
    }

}
