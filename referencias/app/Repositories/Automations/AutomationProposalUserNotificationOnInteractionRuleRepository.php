<?php

namespace App\Repositories\Automations;

use Exception;
use Throwable;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\DatabaseException;
use App\Models\AutomationProposalUserNotificationOnInteractionRule;
use App\DTO\Automations\AutomationProposalUserNotificationInteractionDTO;


class AutomationProposalUserNotificationOnInteractionRuleRepository
{

    public function findRulesByClientAndTriggerType(Client $client, string $triggerType): ?Collection
    {
        return AutomationProposalUserNotificationOnInteractionRule::where([
            'client_id' => $client->id, 'trigger_Type' => $triggerType
        ])->get();
    }


    public function create(AutomationProposalUserNotificationInteractionDTO $dto)
    {
        $data = [
            'client_id' => $dto->client->id,
            'automation_proposal_id' =>  $dto->automationProposal->id,
            'cancelling_tags_ids' => $dto->cancellingTags->pluck('id'),
            'cancelling_status_ids' => $dto->cancellingStatus->pluck('id'),
            'trigger_type' => $dto->triggerType,
            'lead_qualities' => $dto->leadQualities
        ];

        $rule = new AutomationProposalUserNotificationOnInteractionRule($data);
        $rule->saveOrFail();

        return $rule->fresh();
    }


    public function update(
        AutomationProposalUserNotificationOnInteractionRule $rule,
        AutomationProposalUserNotificationInteractionDTO $dto
    ) {
        try {
            DB::beginTransaction();
            $this->delete($rule);
            $rule = $this->create($dto);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    
        return $rule;
    }


    public function delete(AutomationProposalUserNotificationOnInteractionRule $rule)
    {
        $rule->delete();
        $rule->save();
        return $rule->fresh();
    }

}
