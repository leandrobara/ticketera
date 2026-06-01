<?php

namespace App\Repositories\Automations;

use Throwable;
use App\Models\AutomationNewLead;
use Illuminate\Support\Facades\DB;
use App\DTO\Automations\AutomationNewLeadDTO;
use App\Models\AutomationNewLeadTrackingParameter;


class AutomationNewLeadTrackingParameterRepository
{

    public function create(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto)
    {
        try {
            DB::beginTransaction();
            foreach ($dto->trackingParameters as $data) {
                $data['automation_new_lead_id'] = $automationNewLead->id;
                $field = new AutomationNewLeadTrackingParameter($data);
                $field->save();
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }


    public function deleteAllByAutomation(AutomationNewLead $automationNewLead)
    {
        $automationNewLead->trackingParametersToMatch()->delete();
    }

}
