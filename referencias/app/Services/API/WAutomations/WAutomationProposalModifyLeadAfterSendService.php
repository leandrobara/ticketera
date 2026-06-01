<?php

namespace App\Services\API\WAutomations;

use Exception;
use Throwable;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\Client;
use App\Models\WAutomationLog;
use App\Services\API\TagService;
use App\Models\WAutomationProposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\WhatsAppSendingMessage;
use App\Services\API\Actions\LeadService;
use App\Services\Traits\GetClientFromRequest;
use App\Models\WAutomationProposalModifyLeadAfterSendRule;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\DTO\WAutomations\WAutomationPropoposalModifyLeadAfterSendDTO;
use App\Exceptions\Services\WAutomations\WAutomationProposalModifyLeadAfterSendRuleServiceException;
use App\Repositories\WAutomations\WAutomationProposalModifyLeadAfterSendRuleRepository as RuleRepository;


class WAutomationProposalModifyLeadAfterSendService
{

    use GetClientFromRequest;

    private $tagService;
    private $actionsLeadService;
    private $wAutomationLogService;
    private $modifyLeadAfterSendRuleRepository;


    public function __construct(
        RuleRepository $modifyLeadAfterSendRuleRepository,
        LeadService $actionsLeadService,
        TagService $tagService,
        WAutomationLogService $wAutomationLogService
    ) {
        $this->tagService = $tagService;
        $this->actionsLeadService = $actionsLeadService;
        $this->wAutomationLogService = $wAutomationLogService;
        $this->modifyLeadAfterSendRuleRepository = $modifyLeadAfterSendRuleRepository;
    }


    public function findByWAutomationProposal(WAutomationProposal $wAutomationProposal)
    {
        return $this->modifyLeadAfterSendRuleRepository->findByWAutomationProposal($wAutomationProposal);
    }


    public function save(WAutomationPropoposalModifyLeadAfterSendDTO $dto): ?WAutomationProposalModifyLeadAfterSendRule
    {
        $dto->addTags = new Collection();
        $sentProposalTag = $this->tagService->getOrCreateSentProposalTag();

        if ($dto->addSentProposalTag) {
            $dto->addTags->add($sentProposalTag);
        }

        $rule = $this->modifyLeadAfterSendRuleRepository->findRuleByClient(
            $this->getClient()
        );

        if (!$rule) {
            $rule = $this->modifyLeadAfterSendRuleRepository->create($dto);
            return $rule;
        }

        if (!$this->parametersChanged($rule, $dto, $sentProposalTag)) {
            return $rule;
        }

        // If rule was never applied, I can update the row.
        $appliedLog = $this->wAutomationLogService->findOneByWAutomationProposalModifyLeadAfterSendRule($rule);
        if (!$appliedLog) {
            $rule = $this->modifyLeadAfterSendRuleRepository->update($rule, $dto);
            return $rule;
        }

        try {
            DB::beginTransaction();
            // If rule was applied at least once, I soft-delete it and create a new one.
            $this->modifyLeadAfterSendRuleRepository->delete($rule);
            $rule = $this->modifyLeadAfterSendRuleRepository->create($dto);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $serviceException = new WAutomationProposalModifyLeadAfterSendRuleServiceException(
                $e->getMessage(), (int) $e->getCode()
            );
            throw $serviceException;
        }

        return $rule;
    }


    public function parametersChanged(
        WAutomationProposalModifyLeadAfterSendRule $rule,
        WAutomationPropoposalModifyLeadAfterSendDTO $dto,
        Tag $sentProposalTag
    ): bool {
        $ruleHasSentProposalTagAssigned =
            $rule->tagsToAdd->isNotEmpty() &&
            $rule->tagsToAdd->count() == 1 &&
            $rule->tagsToAdd->first()->id == $sentProposalTag->id
        ;
        if (
            $dto->assignStatus &&
            $rule->assign_status_id == $dto->assignStatus->id &&
            $ruleHasSentProposalTagAssigned == $dto->addSentProposalTag
        ) {
            return false;
        }
        return true;
    }


    public function apply(WhatsAppSendingMessage $wapSendingMsg): ?WAutomationLog
    {
        if (!$wapSendingMsg->client || !$wapSendingMsg->client->enabled) {
            throw new Exception('non_existent_or_disabled_client');
        }

        $rule = $this->modifyLeadAfterSendRuleRepository->findRuleByClient($wapSendingMsg->client);
        $wAutomation = $rule->wAutomationProposal;
        if (!$wAutomation->enabled) {
            return null;
        }
        if (!$wapSendingMsg->lead) {
            return null;
        }

        // Se aplica 1 sola vez POR LEAD, no por mensaje.
        $appliedRuleLog = $this->wAutomationLogService->findOneByLeadAndWAutomationProposalModifyLeadAfterSendRule(
            $wapSendingMsg->lead, $rule
        );
        if ($appliedRuleLog) {
            return null;
        }

        // Fix: me aseguro que el EventDispatcher tenga un user = NULL, para que no registre user incorrecto.
        resolve(TimelineEventsDispatcherService::class)->setLoginUser(null);
        
        try {
            DB::beginTransaction();
            
            $this->changeLeadTags($wapSendingMsg->lead, $rule);
            $this->assignLeadStatus($wapSendingMsg->lead, $rule);
            $wAutomationLog = $this->wAutomationLogService->createWAutomationProposalModifyLeadAfterSendLog(
                $wapSendingMsg, $rule
            );

            DB::commit();
            return $wAutomationLog;
        } catch (Throwable $e) {
            // @TODO log this error
            DB::rollBack();
            throw $e;
        }
        return null;
    }


    public function changeLeadTags(Lead $lead, WAutomationProposalModifyLeadAfterSendRule $rule)
    {
        // get lead tags and merge them with tags to add
        $tags = $lead->tags;
        $tags = $tags->merge($rule->tagsToAdd);
        // then I make a diff between tags and tags to remove
        // in order to filter the unwanted tags
        $tagsToRemove = $rule->tagsToRemove;
        $tags = $tags->diff($tagsToRemove);
        if ($tags) {
            $this->actionsLeadService->assignTags($lead, $tags);
        }
        return $lead;
    }


    public function assignLeadStatus(Lead $lead, WAutomationProposalModifyLeadAfterSendRule $rule)
    {
        $status = $rule->statusToAssign;
        if ($status) {
            $this->actionsLeadService->changeStatus($lead, $status);
        }
        return $lead;
    }

}
