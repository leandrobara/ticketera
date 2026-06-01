<?php

namespace App\Repositories\Automations;

use Exception;
use App\Models\Client;
use App\Models\AutomationProposal;
use App\Exceptions\DatabaseException;
use App\Models\AutomationProposalResendRule;
use App\DTO\Automations\AutomationProposalResendDTO;


class AutomationProposalResendRuleRepository
{

    public function findByAutomationProposal(AutomationProposal $automationProposal): ?AutomationProposalResendRule
    {
        return AutomationProposalResendRule::where('automation_proposal_id', $automationProposal->id)->first();
    }


    public function findRuleByClient(Client $client): ?AutomationProposalResendRule
    {
        return AutomationProposalResendRule::where('client_id', $client->id)->first();
    }


    public function findEnabledRuleByClient(Client $client)
    {
        return AutomationProposalResendRule::where(['client_id' => $client->id, 'enabled' => true])->first();
    }


    public function create(AutomationProposalResendDTO $dto): AutomationProposalResendRule
    {
        $data = [
            'enabled' => $dto->enabled,
            'send_hour' => $dto->sendHour,
            'client_id' => $dto->client->id,
            'send_delay_days' => $dto->sendDelayDays,
            'cancelling_enabled' => $dto->cancellingEnabled,
            'send_email_template_id' => $dto->sendEmailTemplate->id,
            'automation_proposal_id' => $dto->automationProposal->id,
            'cancelling_tags_ids' => $dto->cancellingTags->pluck('id'),
            'add_original_attachments' => $dto->addOriginalAttachments,
            'cancelling_status_ids' => $dto->cancellingStatus->pluck('id'),
            'cancel_if_proposal_was_opened' => $dto->cancelIfProposalWasOpened,
            'cancel_if_proposal_was_already_sent' => $dto->cancelIfProposalWasAlreadySent,
        ];
        $rule = new AutomationProposalResendRule($data);
        $rule->saveOrFail();
        return $rule->fresh();
    }


    public function update(
        AutomationProposalResendRule $rule,
        AutomationProposalResendDTO $dto
    ): AutomationProposalResendRule {
        $data = [
            'enabled' => $dto->enabled,
            'send_hour' => $dto->sendHour,
            'send_delay_days' => $dto->sendDelayDays,
            'cancelling_enabled' => $dto->cancellingEnabled,
            'send_email_template_id' => $dto->sendEmailTemplate->id,
            'automation_proposal_id' => $dto->automationProposal->id,
            'cancelling_tags_ids' => $dto->cancellingTags->pluck('id'),
            'add_original_attachments' => $dto->addOriginalAttachments,
            'cancelling_status_ids' => $dto->cancellingStatus->pluck('id'),
            'cancel_if_proposal_was_opened' => $dto->cancelIfProposalWasOpened,
            'cancel_if_proposal_was_already_sent' => $dto->cancelIfProposalWasAlreadySent,
        ];
        $rule->fill($data);
        $rule->saveOrFail();
        return $rule->fresh();
    }


    public function delete(AutomationProposalResendRule $rule): AutomationProposalResendRule
    {
        $rule->delete();
        $rule->save();
        return $rule->fresh();
    }

}
