<?php

namespace App\Repositories\Automations;

use Throwable;
use App\Models\AutomationNewLead;
use Illuminate\Support\Facades\DB;
use App\DTO\Automations\AutomationNewLeadDTO;
use App\Models\AutomationNewLeadCustomFieldMatch;


class AutomationNewLeadCustomFieldMatchRepository
{

    public function create(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto): void
    {
        DB::beginTransaction();
        try {
            foreach ($dto->leadCustomFieldsMatch as $data) {
                $data['automation_new_lead_id'] = $automationNewLead->id;
                $row = new AutomationNewLeadCustomFieldMatch($data);
                $row->save();
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }


    public function deleteAllByAutomation(AutomationNewLead $automationNewLead): void
    {
        $automationNewLead->leadCustomFieldsMatch()->delete();
    }
}


