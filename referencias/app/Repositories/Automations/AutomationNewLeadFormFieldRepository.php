<?php

namespace App\Repositories\Automations;

use Exception;
use Throwable;
use App\Models\AutomationNewLead;
use Illuminate\Support\Facades\DB;
use App\Exceptions\DatabaseException;
use App\Models\AutomationNewLeadFormField;
use App\DTO\Automations\AutomationNewLeadDTO;


class AutomationNewLeadFormFieldRepository
{

    public function create(AutomationNewLead $automationNewLead, AutomationNewLeadDTO $dto)
    {
        try {
            DB::beginTransaction();
            foreach ($dto->formFields as $data) {
                $data['automation_new_lead_id'] = $automationNewLead->id;
                $field = new AutomationNewLeadFormField($data);
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
        $automationNewLead->formFieldsToMatch()->delete();
    }

}
