<?php

namespace App\Services\API\Automations;

use DateTime;
use Exception;
use Throwable;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\Email;
use App\Models\AutomationLog;
use App\Services\API\TagService;
use App\Services\API\TaskService;
use App\Services\API\EmailService;
use App\Models\AutomationProposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\DTO\EmailSystemScheduleParametersDTO;
use App\Services\Traits\GetClientFromRequest;
use App\Helpers\AutomationUserNotificationHelper;
use App\Models\AutomationProposalInteractionRule;
use App\Services\API\Automations\AutomationLogService;
use App\DTO\Automations\AutomationProposalInteractionDTO;
use App\Services\API\Actions\LeadService as ActionsLeadService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\Exceptions\Services\Automations\AutomationNotToReportException;
use App\Repositories\Automations\AutomationProposalInteractionRuleRepository;
use App\Exceptions\Services\Automations\AutomationProposalInteractionServiceException;


class AutomationProposalInteractionService
{

    use GetClientFromRequest;


    public function __construct(
        private AutomationProposalInteractionRuleRepository $automationProposalInteractionRuleRepository,
        private ActionsLeadService $actionsLeadService,
        private TaskService $taskService,
        private TagService $tagService,
        private EmailService $emailService,
        private AutomationUserNotificationHelper $automationUserNotificationHelper,
        private AutomationLogService $automationLogService
    ) {
    }


    public function findByAutomationProposal(AutomationProposal $automationProposal)
    {
        return $this->automationProposalInteractionRuleRepository->findByAutomationProposal($automationProposal);
    }


    // ABM
    public function save(AutomationProposalInteractionDTO $dto): ?AutomationProposalInteractionRule
    {
        $dto->addTags = new Collection();
        $openProposalTag = $this->tagService->getOrCreateOpenedProposalTag();

        if ($dto->addOpenedProposalTag) {
            $dto->addTags->add($openProposalTag);
        }

        $rule = $this->automationProposalInteractionRuleRepository->findRuleByClient($this->getClient());
        if (!$rule) {
            $rule = $this->automationProposalInteractionRuleRepository->create($dto);
            return $rule;
        }

        if (!$this->parametersChanged($rule, $dto, $openProposalTag)) {
            return $rule;
        }

        $ruleWasApplied = $this->automationLogService->findByAutomationProposalInteractionRule($rule)->isNotEmpty();
        if (!$ruleWasApplied) {
            $rule = $this->automationProposalInteractionRuleRepository->update($rule, $dto);
            return $rule;
        }
        try {
            DB::beginTransaction();
            // If rule was applied at least once, I soft-delete it and create a new one.
            $this->automationProposalInteractionRuleRepository->delete($rule);
            $rule = $this->automationProposalInteractionRuleRepository->create($dto);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $serviceException = new AutomationProposalInteractionServiceException(
                $e->getMessage(), (int) $e->getCode()
            );
            throw $serviceException;
        }

        return $rule;
    }


    public function parametersChanged(
        AutomationProposalInteractionRule $rule,
        AutomationProposalInteractionDTO $dto,
        Tag $openProposalTag
    ): bool {
        $ruleHasAddOpenedProposalTag =
            $rule->tagsToAdd->isNotEmpty() &&
            $rule->tagsToAdd->count() === 1 &&
            $rule->tagsToAdd->first()->id == $openProposalTag->id
        ;

        if (
            $rule->assign_status_id == $dto->assignStatus?->id &&
            $ruleHasAddOpenedProposalTag == $dto->addOpenedProposalTag &&
            $rule->send_notification_email_to_user === $dto->sendNotificationEmailToUser &&
            $rule->notify_only_if_lead_quality_is_gt === $dto->notifyOnlyIfLeadQUalityIsGt
        ) {
            return false;
        }

        return true;
    }


    public function applyOpenTrigger(Email $openedEmail): ?AutomationLog
    {
        if (!$openedEmail->lead || !$openedEmail->is_proposal) {
            return null;
        }
        $lead = $openedEmail->lead;
        $rule = $this->automationProposalInteractionRuleRepository->findRuleByClient($openedEmail->client);

        $logExists = $this->automationLogService->findOneByLeadAndEmailAndAutomationProposalInteractionRule(
            $lead, $openedEmail, $rule
        );
        if ($logExists) {
            return null;
        }

        $automation = $rule->automationProposal;
        if (!$automation->enabled) {
            return null;
        }
        $exception = $this->getExceptionIfNotEligible($rule, $openedEmail);
        if ($exception) {
            $log = $this->automationLogService->createAutomationProposalInteractionLog(
                $openedEmail, $rule, $exception
            );
            return $log;
        }

        // Fix: me aseguro que el EventDispatcher tenga un user = NULL, para que no registre user incorrecto.
        resolve(TimelineEventsDispatcherService::class)->setLoginUser(null);

        try {
            DB::beginTransaction();

            $this->addLeadTask($lead, $rule);
            $this->changeLeadTags($lead, $rule);
            $this->assignLeadStatus($lead, $rule);
            $automationLog = $this->automationLogService->createAutomationProposalInteractionLog($openedEmail, $rule);
            $this->sendNotificationEmail($rule, $lead, $openedEmail, $automationLog);

            DB::commit();
            return $automationLog;
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return null;
    }


    public function getExceptionIfNotEligible(
        AutomationProposalInteractionRule $rule,
        Email $openedEmail
    ): Exception | AutomationNotToReportException | null {
        if ($this->leadHasCancellingTag($openedEmail->lead, $rule)) {
            $msg = 'opened_proposal_email_lead_has_cancelling_tag';
            return new AutomationNotToReportException($msg);
        }
        if ($this->leadHasCancellingStatus($openedEmail->lead, $rule)) {
            $msg = 'opened_proposal_email_lead_has_cancelling_status';
            return new AutomationNotToReportException($msg);
        }
        return null;
    }


    public function addLeadTask(Lead $lead, AutomationProposalInteractionRule $rule)
    {
        if ($rule->add_new_task) {
            $daysToExpire = $rule->new_task_days_to_expire ?? 1;
            $attr = [
                'lead_id' => $lead->id,
                'user_id' => $lead->user->id,
                'client_id' => $lead->client_id,
                'title' => $rule->new_task_title,
                'description' => $rule->new_task_description,
                'limit_date' => new DateTime('+' . $daysToExpire . ' days'),
                'is_important' => $rule->new_task_is_important ?? false
            ];
            $this->taskService->create($attr);
        }
    }


    public function changeLeadTags(Lead $lead, AutomationProposalInteractionRule $rule)
    {
        // get lead tags and merge them with tags to add
        $tags = $lead->tags;
        $tags = $tags->merge($rule->tagsToAdd);
        // then I make a diff between tags and tags to remove
        // in order to filter the unwanted tags
        $tagsToRemove = $rule->tagsToRemove;
        $tags = $tags->diff($tagsToRemove);
        if ($tags) {
            $lead = $this->actionsLeadService->setLeadTags($lead, $tags);
        }
        return $lead;
    }


    public function sendNotificationEmail(
        AutomationProposalInteractionRule $rule,
        Lead $lead,
        Email $email,
        AutomationLog $automationLog
    ): ?AutomationProposalInteractionRule {
        if ($rule->send_notification_email_to_user) {
            $disqualifedByQuality = $rule->notify_only_if_lead_quality_is_gt &&
                $rule->notify_only_if_lead_quality_is_gt > $lead->quality;

            if ($disqualifedByQuality) {
                return null;
            }

            $appCustomMetadata = json_encode(['automationLog' => ['id' => $automationLog->id]]);
            $appCustomId = "SYS_CID_{$email->user->client->id}_UID_{$email->user->id}_AUT_PROP_INTERACT_NOTIF";

            $dto = new EmailSystemScheduleParametersDTO();
            $dto->appCustomId = $appCustomId;
            $dto->appCustomMetadata = $appCustomMetadata;
            $dto->sendDate = (new DateTime())->format('Y-m-d\TH:i:sP');
            $dto->body = $this->automationUserNotificationHelper->getNotificationEmailBody($email);
            $dto->subject = $this->automationUserNotificationHelper->getNotificationEmailSubject($email);

            $this->emailService->setRequestUser($email->user);
            $this->emailService->scheduleSystemEmail($email->user->email, $dto);

            return $rule;
        }

        return null;
    }


    public function assignLeadStatus(Lead $lead, AutomationProposalInteractionRule $rule): Lead
    {
        if (!$rule->assign_status_id || !$rule->statusToAssign) {
            return $lead;
        }
        $this->actionsLeadService->changeStatus($lead, $rule->statusToAssign);
        return $lead->fresh();
    }


    public function leadHasCancellingStatus(Lead $lead, AutomationProposalInteractionRule $rule): bool
    {
        return $rule->cancellingStatusList->contains($lead->status);
    }


    public function leadHasCancellingTag(Lead $lead, AutomationProposalInteractionRule $rule): bool
    {
        return $rule->cancellingTags->intersect($lead->tags)->isNotEmpty();
    }

}
