<?php

namespace App\Services\API\Views;

use App\Services\API\TagService;
use App\Models\AutomationProposal;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\Automations\AutomationProposalRepository;


class AutomationProposalService
{

    use GetClientFromRequest;


    public function __construct(
        AutomationProposalRepository $automationProposalRepository,
        TagService $tagService
    ) {
        $this->tagService = $tagService;
        $this->automationProposalRepository = $automationProposalRepository;
    }


    public function findAutomationProposal(): ?AutomationProposal
    {
        $automation = $this->automationProposalRepository->findByClient($this->getClient());

        $openedProposalTag = $automation->interactionRule->tagsToAdd->pluck('id')->toArray();
        $sentProposalTag = $automation->modifyLeadAfterSendRule->tagsToAdd->pluck('id')->toArray();

        if (
            $openedProposalTag &&
            in_array($this->tagService->getOrCreateOpenedProposalTag()->id, $openedProposalTag)
        ) {
            $automation->interactionRule->add_opened_proposal_tag = true;
        }

        if (
            $sentProposalTag &&
            in_array($this->tagService->getOrCreateSentProposalTag()->id, $sentProposalTag)
        ) {
            $automation->modifyLeadAfterSendRule->add_sent_proposal_tag = true;
        }

        return $automation;
    }

}
