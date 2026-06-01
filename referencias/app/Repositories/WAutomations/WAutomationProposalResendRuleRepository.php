<?php

namespace App\Repositories\WAutomations;

use Exception;
use App\Models\Client;
use App\Models\WAutomationProposal;
use App\Exceptions\DatabaseException;
use App\Models\WAutomationProposalResendRule;
use App\DTO\WAutomations\WAutomationProposalResendDTO;


class WAutomationProposalResendRuleRepository
{

    public function findByWAutomationProposal(WAutomationProposal $wAutomationProposal): WAutomationProposalResendRule
    {
        return WAutomationProposalResendRule::where('wautomation_proposal_id', $wAutomationProposal->id)->first();
    }


    public function findRuleByClient(Client $client): WAutomationProposalResendRule
    {
        return WAutomationProposalResendRule::where('client_id', $client->id)->first();
    }


    public function findEnabledRuleByClient(Client $client): ?WAutomationProposalResendRule
    {
        return WAutomationProposalResendRule::where(['client_id' => $client->id, 'enabled' => true])->first();
    }


    public function create(WAutomationProposalResendDTO $dto): WAutomationProposalResendRule
    {
        $data = [
            'enabled' => $dto->enabled,
            'send_hour' => $dto->sendHour,
            'client_id' => $dto->client->id,
            'send_delay_days' => $dto->sendDelayDays,
            'cancelling_enabled' => $dto->cancellingEnabled,
            'do_not_send_weekends' => $dto->doNotSendWeekends,
            'wautomation_proposal_id' => $dto->wAutomationProposal->id,
            'cancelling_tags_ids' => $dto->cancellingTags->pluck('id'),
            'add_original_attachments' => $dto->addOriginalAttachments,
            'cancelling_status_ids' => $dto->cancellingStatus->pluck('id'),
            'send_whatsapp_template_id' => $dto->sendWhatsAppTemplate->id,
            'cancel_if_proposal_was_already_sent' => $dto->cancelIfProposalWasAlreadySent,
        ];
        $rule = new WAutomationProposalResendRule($data);
        $rule->saveOrFail();
        return $rule->fresh();
    }


    public function update(
        WAutomationProposalResendRule $rule,
        WAutomationProposalResendDTO $dto
    ): WAutomationProposalResendRule {
        $data = [
            'enabled' => $dto->enabled,
            'send_hour' => $dto->sendHour,
            'send_delay_days' => $dto->sendDelayDays,
            'cancelling_enabled' => $dto->cancellingEnabled,
            'do_not_send_weekends' => $dto->doNotSendWeekends,
            'wautomation_proposal_id' => $dto->wAutomationProposal->id,
            'cancelling_tags_ids' => $dto->cancellingTags->pluck('id'),
            'add_original_attachments' => $dto->addOriginalAttachments,
            'cancelling_status_ids' => $dto->cancellingStatus->pluck('id'),
            'send_whatsapp_template_id' => $dto->sendWhatsAppTemplate->id,
            'cancel_if_proposal_was_already_sent' => $dto->cancelIfProposalWasAlreadySent,
        ];
        $rule->fill($data);
        $rule->saveOrFail();
        return $rule->fresh();
    }


    public function delete(WAutomationProposalResendRule $rule): WAutomationProposalResendRule
    {
        $rule->delete();
        $rule->save();
        return $rule->fresh();
    }

}
