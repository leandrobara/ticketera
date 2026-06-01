<?php

namespace App\Services\API\Automations;

use Exception;
use Throwable;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\AutomationLog;
use App\Services\API\TagService;
use App\Models\AutomationProposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\API\Actions\LeadService;
use App\Services\Traits\GetClientFromRequest;
use App\Models\AutomationProposalModifyLeadAfterSendRule;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\DTO\Automations\AutomationPropoposalModifyLeadAfterSendDTO;
use App\Exceptions\Services\Automations\AutomationProposalModifyLeadAfterSendRuleServiceException;
use App\Repositories\Automations\AutomationProposalModifyLeadAfterSendRuleRepository as RuleRepository;


class AutomationProposalModifyLeadAfterSendService
{

    use GetClientFromRequest;

    private $tagService;
    private $actionsLeadService;
    private $automationLogService;
    private $modifyLeadAfterSendRuleRepository;


    public function __construct(
        RuleRepository $modifyLeadAfterSendRuleRepository,
        LeadService $actionsLeadService,
        TagService $tagService,
        AutomationLogService $automationLogService
    ) {
        $this->tagService = $tagService;
        $this->actionsLeadService = $actionsLeadService;
        $this->automationLogService = $automationLogService;
        $this->modifyLeadAfterSendRuleRepository = $modifyLeadAfterSendRuleRepository;
    }


    public function findByAutomationProposal(AutomationProposal $automationProposal)
    {
        return $this->modifyLeadAfterSendRuleRepository->findByAutomationProposal($automationProposal);
    }


    public function save(AutomationPropoposalModifyLeadAfterSendDTO $dto): ?AutomationProposalModifyLeadAfterSendRule
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
        $lastAppliedLog = $this->automationLogService->findLastOneByAutomationProposalModifyLeadAfterSendRule($rule);
        if (!$lastAppliedLog) {
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
            $serviceException = new AutomationProposalModifyLeadAfterSendRuleServiceException(
                $e->getMessage(), (int) $e->getCode()
            );
            throw $serviceException;
        }

        return $rule;
    }


    public function parametersChanged(
        AutomationProposalModifyLeadAfterSendRule $rule,
        AutomationPropoposalModifyLeadAfterSendDTO $dto,
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


    public function apply(Lead $lead): ?AutomationLog
    {
        $rule = $this->modifyLeadAfterSendRuleRepository->findRuleByClient($lead->client);
        $automation = $rule->automationProposal;
        if (!$automation->enabled) {
            return null;
        }
        $appliedRuleLog = $this->automationLogService->findOneByLeadAndAutomationProposalModifyLeadAfterSend(
            $lead, $rule
        );
        if ($appliedRuleLog) {
            return null;
        }

        // Fix: me aseguro que el EventDispatcher tenga un user = NULL, para que no registre user incorrecto.
        resolve(TimelineEventsDispatcherService::class)->setLoginUser(null);
        
        try {
            $lead = $lead->fresh();
            DB::beginTransaction();
            $this->changeLeadTags($lead, $rule);
            $this->assignLeadStatus($lead, $rule);
            $automationLog = $this->automationLogService->createAutomationProposalModifyLeadAfterSendLog(
                $lead, $rule
            );
            DB::commit();

            return $automationLog;
        } catch (Throwable $e) {
            // @TODO log this error
            DB::rollBack();
            throw $e;
        }
        return null;
    }


    public function changeLeadTags(Lead $lead, AutomationProposalModifyLeadAfterSendRule $rule)
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


    public function assignLeadStatus(Lead $lead, AutomationProposalModifyLeadAfterSendRule $rule)
    {
        $status = $rule->statusToAssign;
        if ($status) {
            $this->actionsLeadService->changeStatus($lead, $status);
        }
        return $lead;
    }

}
