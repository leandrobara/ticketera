<?php

namespace App\Services\API\Views;

use App\Services\API\TagService;
use App\Models\WAutomationProposal;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\WAutomations\WAutomationProposalRepository;


class WAutomationProposalService
{

    use GetClientFromRequest;


    public function __construct(
        WAutomationProposalRepository $wAutomationProposalRepository,
        TagService $tagService
    ) {
        $this->tagService = $tagService;
        $this->wAutomationProposalRepository = $wAutomationProposalRepository;
    }


    public function findWAutomationProposal(): ?WAutomationProposal
    {
        $sentProposalTagId = $this->tagService->getOrCreateSentProposalTag()->id;
        $wAutomation = $this->wAutomationProposalRepository->findByClient($this->getClient());
        $tagIdsToAdd = $wAutomation->modifyLeadAfterSendRule->tagsToAdd->pluck('id')->toArray();

        if ($tagIdsToAdd && in_array($sentProposalTagId, $tagIdsToAdd)) {
            $wAutomation->modifyLeadAfterSendRule->add_sent_proposal_tag = true;
        }
        return $wAutomation;
    }

}
