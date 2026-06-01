<?php

namespace App\Repositories\Automations;

use Exception;
use App\Models\Client;
use App\Models\AutomationProposal;
use Illuminate\Support\Facades\DB;
use App\Exceptions\DatabaseException;
use App\Models\AutomationProposalInteractionRule;
use App\DTO\Automations\AutomationProposalInteractionDTO;
use App\DTO\Automations\AutomationProposalRulesDTO;


class AutomationProposalInteractionRuleRepository
{

    public function findByAutomationProposal(
        AutomationProposal $automationProposal
    ): ?AutomationProposalInteractionRule {
        $id = $automationProposal->id;
        return AutomationProposalInteractionRule::where(['automation_proposal_id' => $id])->first();
    }


    public function findRuleByClient(Client $client)
    {
        return AutomationProposalInteractionRule::where('client_id', $client->id)->first();
    }


    public function create(AutomationProposalInteractionDTO $dto)
    {
        $data = [
            'client_id' => $dto->client->id,
            'trigger_type'  => $dto->triggerType,
            'add_tags_ids'  => $dto->addTags->pluck('id'),
            'assign_status_id'  => $dto->assignStatus?->id,
            'remove_tags_ids'  => $dto->removeTags->pluck('id'),
            'automation_proposal_id' => $dto->automationProposal->id,
            'cancelling_tags_ids'  => $dto->cancellingTags->pluck('id'),
            'cancelling_status_ids'  => $dto->cancellingStatus->pluck('id'),
            'send_notification_email_to_user' =>  $dto->sendNotificationEmailToUser,
            'notify_only_if_lead_quality_is_gt' => $dto->notifyOnlyIfLeadQUalityIsGt,
        ];
        $automation = new AutomationProposalInteractionRule($data);
        $automation->saveOrFail();
        return $automation->fresh();
    }


    public function update(AutomationProposalInteractionRule $rule, AutomationProposalInteractionDTO $dto)
    {
        $data = [
            'client_id' => $dto->client->id,
            'trigger_type'  => $dto->triggerType,
            'add_tags_ids'  => $dto->addTags->pluck('id'),
            'assign_status_id'  => $dto->assignStatus?->id,
            'remove_tags_ids'  => $dto->removeTags->pluck('id'),
            'automation_proposal_id' => $dto->automationProposal->id,
            'cancelling_tags_ids'  => $dto->cancellingTags->pluck('id'),
            'cancelling_status_ids'  => $dto->cancellingStatus->pluck('id'),
            'send_notification_email_to_user' =>  $dto->sendNotificationEmailToUser,
            'notify_only_if_lead_quality_is_gt' => $dto->notifyOnlyIfLeadQUalityIsGt,
        ];
        $rule->fill($data);
        $rule->saveOrFail();
        return $rule;
    }


    public function delete(AutomationProposalInteractionRule $rule)
    {
        $rule->delete();
        $rule->save();
        return $rule->fresh();
    }

}
