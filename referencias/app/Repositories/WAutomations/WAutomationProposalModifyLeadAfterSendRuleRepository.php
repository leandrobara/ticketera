<?php

namespace App\Repositories\WAutomations;

use Exception;
use App\Models\Client;
use App\Models\WAutomationProposal;
use App\Exceptions\DatabaseException;
use App\Models\WAutomationProposalModifyLeadAfterSendRule;
use App\DTO\WAutomations\WAutomationPropoposalModifyLeadAfterSendDTO;


class WAutomationProposalModifyLeadAfterSendRuleRepository
{

    public function findByWAutomationProposal(
        WAutomationProposal $wAutomationProposal
    ): WAutomationProposalModifyLeadAfterSendRule {
        $id = $wAutomationProposal->id;
        return WAutomationProposalModifyLeadAfterSendRule::where('wautomation_proposal_id', $id)->first();
    }


    public function findRuleByClient(Client $client): WAutomationProposalModifyLeadAfterSendRule
    {
        return WAutomationProposalModifyLeadAfterSendRule::where('client_id', $client->id)->first();
    }


    public function create(WAutomationPropoposalModifyLeadAfterSendDTO $dto)
    {
        $data = [
            'client_id' => $dto->client->id,
            'add_tags_ids' => $dto->addTags->pluck('id'),
            'remove_tags_ids' => $dto->removeTags->pluck('id'),
            'wautomation_proposal_id' => $dto->wAutomationProposal->id,
            'assign_status_id'  => $dto->assignStatus ? $dto->assignStatus->id : null,
        ];
        $wAutomation = new WAutomationProposalModifyLeadAfterSendRule($data);
        $wAutomation->saveOrFail();
        return $wAutomation->fresh();
    }


    public function update(
        WAutomationProposalModifyLeadAfterSendRule $rule,
        WAutomationPropoposalModifyLeadAfterSendDTO $dto
    ): WAutomationProposalModifyLeadAfterSendRule {
        $data = [
            'client_id' => $dto->client->id,
            'add_tags_ids' => $dto->addTags->pluck('id'),
            'remove_tags_ids' => $dto->removeTags->pluck('id'),
            'wautomation_proposal_id' => $dto->wAutomationProposal->id,
            'assign_status_id'  => $dto->assignStatus ? $dto->assignStatus->id : null,
        ];
        $rule->fill($data);
        $rule->saveOrFail();
        return $rule;
    }


    public function delete(WAutomationProposalModifyLeadAfterSendRule $rule)
    {
        $rule->delete();
        $rule->save();
        return $rule->fresh();
    }

}
