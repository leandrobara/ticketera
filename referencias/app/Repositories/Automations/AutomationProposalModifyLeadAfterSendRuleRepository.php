<?php

namespace App\Repositories\Automations;

use Exception;
use App\Models\Client;
use App\Models\AutomationProposal;
use App\Exceptions\DatabaseException;
use App\Models\AutomationProposalModifyLeadAfterSendRule;
use App\DTO\Automations\AutomationPropoposalModifyLeadAfterSendDTO;

class AutomationProposalModifyLeadAfterSendRuleRepository
{

    public function findByAutomationProposal(AutomationProposal $automationProposal)
    {
        $id = $automationProposal->id;
        return AutomationProposalModifyLeadAfterSendRule::where('automation_proposal_id', $id)->first();
    }


    public function findRuleByClient(Client $client)
    {
        return AutomationProposalModifyLeadAfterSendRule::where('client_id', $client->id)->first();
    }


    public function create(AutomationPropoposalModifyLeadAfterSendDTO $dto)
    {
        $data = [
            'client_id' => $dto->client->id,
            'automation_proposal_id' => $dto->automationProposal->id,
            'add_tags_ids' => $dto->addTags->pluck('id'),
            'remove_tags_ids' => $dto->removeTags->pluck('id'),
            'assign_status_id'  => $dto->assignStatus ? $dto->assignStatus->id : null,
        ];
        $automation = new AutomationProposalModifyLeadAfterSendRule($data);
        $automation->saveOrFail();
        return $automation->fresh();
    }


    public function update(
        AutomationProposalModifyLeadAfterSendRule $rule,
        AutomationPropoposalModifyLeadAfterSendDTO $dto
    ): AutomationProposalModifyLeadAfterSendRule {
        $data = [
            'client_id' => $dto->client->id,
            'automation_proposal_id' => $dto->automationProposal->id,
            'add_tags_ids' => $dto->addTags->pluck('id'),
            'remove_tags_ids' => $dto->removeTags->pluck('id'),
            'assign_status_id'  => $dto->assignStatus ? $dto->assignStatus->id : null,
        ];
        $rule->fill($data);
        $rule->saveOrFail();
        return $rule;
    }


    public function delete(AutomationProposalModifyLeadAfterSendRule $rule)
    {
        $rule->delete();
        $rule->save();
        return $rule->fresh();
    }

}
