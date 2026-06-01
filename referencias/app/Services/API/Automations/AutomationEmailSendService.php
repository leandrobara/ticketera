<?php

namespace App\Services\API\Automations;

use DateTime;
use Exception;
use Throwable;
use DateTimeZone;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\Email;
use App\Models\Client;
use App\Models\LeadSale;
use App\Models\Notification;
use App\Models\AutomationLog;
use App\Models\MongoDB\EventLog;
use App\Services\API\UserService;
use App\Services\API\LeadService;
use Illuminate\Support\Facades\DB;
use App\Services\API\EmailService;
use Illuminate\Support\Collection;
use App\Models\AutomationEmailSend;
use App\Services\API\StatusService;
use App\Services\API\LeadSaleService;
use App\Services\API\EventsLogService;
use App\DTO\EmailScheduleParametersDTO;
use App\Models\AutomationEmailSendStep;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\Automations\AutomationEmailSendDTO;
use App\DTO\AutomationEmailClientyLogsResponseDTO;
use App\Services\API\Notifications\NotificationService;
use App\Services\API\Dispatchers\EmailEventsDispatcherService;
use App\Services\API\Actions\LeadService as ActionsLeadService;
use App\Services\API\Automations\AutomationEmailSendStepService;
use App\Exceptions\Services\Automations\AutomationEmailSendServiceException;
use App\Exceptions\Services\EmailService\EmailSendValidationUserNotEnabledException;
use App\Repositories\Automations\AutomationEmailSendRepository as AutomationEmailSendRepo;
use App\Repositories\Cache\AutomationEmailSendRepositoryCache as AutomationEmailSendRepoCache;


class AutomationEmailSendService
{

    use GetClientFromRequest, GetUserFromRequest;

    const LIMIT_WINDOW_HOURS = 6;
    const LIMIT_WINDOW_MINUTES_SAME_DAY = 45;


    public function __construct(
        protected readonly AutomationEmailSendRepo | AutomationEmailSendRepoCache $automationEmailSendRepository,
        protected readonly AutomationLogService $automationLogService,
        protected readonly UserService $userService,
        protected readonly LeadService $leadService,
        protected readonly EmailService $emailService,
        protected readonly StatusService $statusService,
        protected readonly LeadSaleService $leadSaleService,
        protected readonly EventsLogService $eventsLogService,
        protected readonly NotificationService $notificationService,
        protected readonly EmailEventsDispatcherService $emailEventsDispatcherService,
        protected readonly AutomationEmailSendStepService $automationEmailSendStepService,
    ) {
    }


    public function save(AutomationEmailSendDTO $dto): AutomationEmailSend
    {
        $automation = $this->automationEmailSendRepository->findOneByClientAndTrigger(
            $this->getClient(), $dto->triggerType
        );
        if (!$automation) {
            $automation = $this->create($dto);
            return $automation;
        }
        if (!$this->parametersChanged($automation, $dto)) {
            return $automation;
        }
        $automation = $this->update($automation, $dto);
        return $automation;
    }


    public function create(AutomationEmailSendDTO $dto): AutomationEmailSend
    {
        return $this->automationEmailSendRepository->create($dto);
    }


    public function update(AutomationEmailSend $automation, AutomationEmailSendDTO $dto)
    {
        if (!$this->parametersChanged($automation, $dto)) {
            return $automation;
        }

        // If rule was never applied, I can update the row.
        $logs = $this->automationLogService->findByAutomationEmailSend($automation, ['limit' => 1]);
        $ruleWasApplied = $logs->isNotEmpty();
        if (!$ruleWasApplied) {
            $automation = $this->automationEmailSendRepository->update($automation, $dto);
            return $automation;
        }

        try {
            DB::beginTransaction();

            // If rule was applied at least once, I soft-delete it and create a new one.
            $this->automationEmailSendRepository->delete($automation);
            $newAutomation = $this->automationEmailSendRepository->create($dto);
            $newSteps = $this->automationEmailSendStepService->cloneAutomationEmailSendSteps(
                $automation, $newAutomation
            );
            $this->automationEmailSendStepService->deleteAllByAutomationEmailSend($automation);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $newAutomation;
    }


    public function delete(AutomationEmailSend $automation): AutomationEmailSend
    {
        try {
            DB::beginTransaction();
            $this->automationEmailSendStepService->deleteAllByAutomationEmailSend($automation);
            $deletedAutomation = $this->automationEmailSendRepository->delete($automation);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->emailEventsDispatcherService->dispatchSendDeletedAutomationEmailAlertJob(
            $deletedAutomation, $this->getUser()
        );
        return $deletedAutomation;
    }


    public function enable(AutomationEmailSend $automation): AutomationEmailSend
    {
        return $this->automationEmailSendRepository->enable($automation);
    }


    public function disable(AutomationEmailSend $automation): AutomationEmailSend
    {
        $disabledAutomation = $this->automationEmailSendRepository->disable($automation);
        
        $this->emailEventsDispatcherService->dispatchSendDisabledAutomationEmailAlertJob(
            $disabledAutomation, $this->getUser()
        );
        return $disabledAutomation;
    }


    public function findAutomationsByClient(?Client $client = null): Collection
    {
        $client = $client ?? $this->getClient();
        return $this->automationEmailSendRepository->findByClient($client);
    }


    public function parametersChanged(AutomationEmailSend $automation, AutomationEmailSendDTO $dto)
    {
        $cancellingTags = $automation->cancellingTags->pluck('id')->toArray();
        $cancellingStatus = $automation->cancellingStatus->pluck('id')->toArray();
        $triggeringTags =  $automation->triggeringTags->pluck('id')->toArray();
        $triggeringStatus = $automation->triggeringStatus->pluck('id')->toArray();

        if (
            $cancellingTags == $dto->cancellingTags->pluck('id')->toArray() &&
            $cancellingStatus == $dto->cancellingStatus->pluck('id')->toArray() &&
            $triggeringTags == $dto->triggeringTags->pluck('id')->toArray() &&
            $triggeringStatus == $dto->triggeringStatus->pluck('id')->toArray() &&
            $automation->enabled == $dto->enabled &&
            $automation->name == $dto->name &&
            $automation->trigger_type == $dto->triggerType &&
            $automation->cancel_if_sequence_was_sent == $dto->cancelIfSequenceWasSent &&
            $automation->do_not_send_weekends == $dto->doNotSendWeekends
        ) {
            return false;
        }

        return true;
    }


    public function findAfterSentProposalAutomationByClient(Client $client): ?AutomationEmailSend
    {
        return $this->automationEmailSendRepository->findOneByClientAndTrigger($client, 'after_sent_proposal');
    }


    public function findAfterSaleAutomationByClient(Client $client): ?AutomationEmailSend
    {
        return $this->automationEmailSendRepository->findOneByClientAndTrigger($client, 'after_sale');
    }


    public function findAfterTagsStatusChangeAutomationsByClient(Client $client): ?Collection
    {
        return $this->automationEmailSendRepository->findByClientAndTrigger($client, 'after_tag_status_change');
    }


    /**
     * Automation Implementation
     */
    public function apply(AutomationEmailSend $automation): Collection
    {
        if (!$automation->enabled) {
            return new Collection();
        }
        if ($this->isWeekendAndCanNotRun($automation)) {
            return new Collection();
        }
        $automationLogs = $this->applyByTriggerType($automation);
        return $automationLogs;
    }


    public function applyByTriggerType(AutomationEmailSend $automation): Collection
    {
        if ($automation->isAfterSentProposalType) {
            return $this->applyEmailSendAfterSentProposal($automation);
        }
        if ($automation->isAfterSaleType) {
            return $this->applyEmailSendAfterSale($automation);
        }
        if ($automation->isAfterTagOrStatusChangeType) {
            throw new Exception('This method process was migrated to the controller');
        }
        return new Collection();
    }


    private function applyEmailSendAfterSentProposal(AutomationEmailSend $automation): Collection
    {
        $automationLogs = new Collection();
        
        // Se usa servicio para que busque en Redis cache
        // $steps = $automation->automationEmailSendSteps;
        $steps = $this->automationEmailSendStepService->findByAutomationEmailSend($automation);
        if (!$steps) {
            return $automationLogs;
        }

        foreach ($steps as $step) {
            if (!$this->isInHourToApply($step)) {
                continue;
            }

            $proposals = $this->findSentProposals($automation, $step);
            $proposals = $this->filterProposalsEnabledToSend($proposals, $step);
            $proposals = $this->filterAlreadyAppliedProposalsByStep($proposals, $step);

            if ($proposals->isEmpty()) {
                continue;
            }

            $enabledProposals = $this->filterProposalsByConditions($automation, $step, $proposals);

            $rejectedProposals = $proposals->diff($enabledProposals);

            $nonAppliedAutomationLogs = $this->storeNonAppliedAutomationLogsByRejectedProposals(
                $rejectedProposals, $step
            );
            $this->storeNotEnabledUsersEmailSendNotificationsFromNonAppliedLogs($nonAppliedAutomationLogs, $step);

            $sentEmails = $this->sendEmailsAndStoreLog($enabledProposals, $step);

            $appliedAutomationLogs = $sentEmails->map(function ($sentEmail) {
                return $sentEmail->automationLog;
            });

            $automationLogs = $automationLogs->merge($appliedAutomationLogs);
            $automationLogs = $automationLogs->merge($nonAppliedAutomationLogs);
        }

        return $automationLogs;
    }


    private function applyEmailSendAfterSale(AutomationEmailSend $automation): Collection
    {
        $automationLogs = new Collection();
        
        // Se usa servicio para que busque en Redis cache
        // $steps = $automation->automationEmailSendSteps;
        $steps = $this->automationEmailSendStepService->findByAutomationEmailSend($automation);
        if (!$steps) {
            return $automationLogs;
        }

        foreach ($steps as $step) {
            if (!$this->isInHourToApply($step)) {
                continue;
            }

            $leadSales = $this->findLeadSales($automation, $step);
            $leadSales = $this->filterSalesEnabledToSend($leadSales, $step);
            $leadSales = $this->filterAlreadyAppliedSalesByStep($leadSales, $step);

            // esto no esta bien
            if ($leadSales->isEmpty()) {
                continue;
            }

            $enabledSales = $this->filterSalesByConditions($automation, $step, $leadSales);

            $rejectedSales = $leadSales->diff($enabledSales);

            // DB::beginTransaction();

            $nonAppliedAutomationLogs = $this->storeNonAppliedAutomationLogsByRejectedLeadSales(
                $rejectedSales, $step
            );

            $this->storeNotEnabledUsersEmailSendNotificationsFromNonAppliedLogs($nonAppliedAutomationLogs, $step);
            $sentEmails = $this->sendEmailsAndStoreLog($enabledSales, $step);

            // DB::commit();

            $appliedAutomationLogs = $sentEmails->map(function ($sentEmail) {
                return $sentEmail->automationLog;
            });
            $automationLogs = $automationLogs->merge($appliedAutomationLogs);
            $automationLogs = $automationLogs->merge($nonAppliedAutomationLogs);
        }
        return $automationLogs;
    }


    public function sendEventLogsEmailsAndStoreLog(
        Collection $enabledEventLogs,
        AutomationEmailSendStep $step
    ): Collection {
        $eventLogsGroupedByLead = $enabledEventLogs->groupBy('log.lead.id');
        
        $opts = [
            'with' => ['user', 'leadContactEmails'],
            'fields' => ['id', 'user_id', 'client_id', 'status_id'],
        ];
        $leadIds = $enabledEventLogs->pluck('log.lead.id')->unique();
        $leads = $this->leadService->findByIds($leadIds, $opts);
        $leads = $leads->mapWithKeys(fn (Lead $lead) => [$lead->id => $lead]);

        $sentEmails = new Collection();
        foreach ($eventLogsGroupedByLead as $leadEventLogs) {
            $leadId = $leadEventLogs->first()->log['lead']['id'];
            $lead = $leads->get($leadId);

            $automationLog = $this->storeAutomationLog(
                $applied = true, $lead, $step, $leadSale = null, $proposal = null, $leadEventLogs
            );

            try {
                DB::beginTransaction();
                $emails = $this->sendEmailToLead($lead, $step, $automationLog);
                $sentEmails = $sentEmails->merge($emails);

                $this->executePostAutomationAction($lead, $step);
                DB::commit();
            } catch (EmailSendValidationUserNotEnabledException $e) {
                DB::rollBack();
                $automationLog['exception'] = $e->__toString();
                $this->markAutomationLogAsNotApplied($automationLog);
                $this->storeNotEnabledUserEmailSendNotification($automationLog);
            } catch (Throwable $e) {
                DB::rollBack();
                $automationLog['exception'] = $e->__toString();
                $this->markAutomationLogAsNotApplied($automationLog);
                throw $e;
            }
        }
        return $sentEmails;
    }


    public function findEmailSendAfterTagStatusChangeEventLogs(AutomationEmailSendStep $step): Collection
    {
        $automation = $step->automationEmailSend;
        if (!$automation->enabled) {
            throw new Exception("Automation ID: {$automation->id} is not enabled");
        }
        if (!$automation->isAfterTagOrStatusChangeType) {
            throw new Exception("Invalid Automation ID: {$automation->id} trigger type");
        }
        if ($automation->isTagTriggered && $automation->triggeringTags->isEmpty()) {
            throw new Exception("Automation ID: {$automation->id} has not triggering tags");
        }
        if ($automation->isStatusTriggered && $automation->triggeringStatus->isEmpty()) {
            throw new Exception("Automation ID: {$automation->id} has not triggering status");
        }

        $eventLogs = $this->findEventLogsByStep($step);
        $eventLogs = $this->filterEventLogsEnabledToSend($eventLogs, $step);
        $eventLogs = $this->filterAlreadyAppliedEventLogsByStep($eventLogs, $step);

        $enabledEventLogs = $this->filterEventLogsByConditions($step, $eventLogs);
        $enabledEventLogIds = $enabledEventLogs->pluck('id')->mapWithKeys(fn ($logId) => [$logId => $logId]);
        $rejectedEventLogs = $eventLogs->filter(
            function (EventLog $eventLog) use ($enabledEventLogIds) {
                return !$enabledEventLogIds->has($eventLog->id);
            }
        );
        $result = collect([
            'enabledEventLogs' => $enabledEventLogs,
            'rejectedEventLogs' => $rejectedEventLogs,
        ]);
        return $result;
    }


    public function storeEmailSendAfterTagStatusChangeRejectedEventLogs(
        Collection $rejectedEventLogs,
        AutomationEmailSendStep $step
    ): Collection {
        $isFullyApplied = false;
        $rejectedProposal = null;
        $rejectedLeadSale = null;

        $nonAppliedAutomationLogs = new Collection();
        $rejectedEventLogsGroupedByLead = $rejectedEventLogs->groupBy('log.lead.id');

        foreach ($rejectedEventLogsGroupedByLead as $rejectedLeadEventLogs) {
            $lead = new Lead(['client_id' => $rejectedLeadEventLogs->first()->log['client_id']]);
            $lead->id = $rejectedLeadEventLogs->first()->log['lead']['id'];

            $nonAppliedAutomationLog = $this->storeAutomationLog(
                $isFullyApplied, $lead, $step, $rejectedLeadSale, $rejectedProposal, $rejectedLeadEventLogs
            );
            $nonAppliedAutomationLogs->push($nonAppliedAutomationLog);
        }
        return $nonAppliedAutomationLogs;
    }


    public function storeNotEnabledUsersEmailSendNotificationsFromNonAppliedLogs(
        Collection $nonAppliedAutomationLogs,
        AutomationEmailSendStep $step
    ): Collection {
        $notifications = new Collection();
        if ($nonAppliedAutomationLogs->isEmpty()) {
            return $notifications;
        }
        
        $opts = ['fields' => ['id', 'user_id'], 'with' => ['user']];
        $leadIds = $nonAppliedAutomationLogs->pluck('lead_id')->unique();
        $leads = $this->leadService->findByIds($leadIds, $opts);
        $leads = $leads->mapWithKeys(fn (Lead $lead) => [$lead->id => $lead]);
        
        $notifiedUserIds = new Collection();
        foreach ($nonAppliedAutomationLogs as $nonAppliedAutomationLog) {
            $lead = $leads->get($nonAppliedAutomationLog->lead_id);
            if (!$lead) {
                continue;
            }
            $isAlreadyNotifiedUser = $notifiedUserIds->has($lead->user_id);
            if ($isAlreadyNotifiedUser) {
                continue;
            }

            $userVerified = $this->isLeadUserVerifiedToSendEmails($lead);
            if (!$userVerified) {
                $notification = $this->storeNotEnabledUserEmailSendNotification($nonAppliedAutomationLog);
                $notifications->push($notification);

                $notifiedUserIds->put($lead->user_id, $lead->user_id);
            }
        }
        return $notifications;
    }


    /******************************************
     * Automation Filters Functions
     ******************************************/

    private function findEventLogsByStep(AutomationEmailSendStep $step): Collection
    {
        $automation = $step->automationEmailSend;
        $dateEnd = $this->getEndDateToSearchEvents($step);
        $dateStart = $this->getStartDateToSearchEvents($automation, $step);
        $eventLogs = $this->eventsLogService->findStatusOrTagChangeEventLogsByAutomation(
            $automation, $dateStart, $dateEnd
        );

        // Filtro de protección extra.
        $eventLogs = $eventLogs->filter(
            function (EventLog $eventLog) use ($automation, $dateStart, $dateEnd) {
                if ($automation->isTagTriggered) {
                    if ($eventLog->event != 'lead_tag_added') {
                        return false;
                    }
                    $tagId = $eventLog->log['tag']['id'];
                    $tagIdExists = $automation->triggeringTags->pluck('id')->contains($tagId);
                    if (!$tagIdExists) {
                        return false;
                    }
                }
                if ($automation->isStatusTriggered) {
                    if ($eventLog->event != 'lead_status_updated') {
                        return false;
                    }
                    $statusId = $eventLog->log['status']['id'];
                    $statusIdExists = $automation->triggeringStatus->pluck('id')->contains($statusId);
                    if (!$statusIdExists) {
                        return false;
                    }
                }
                
                $eventLogCreatedDate = $eventLog->createdAt->toDateTime();
                $isCorrectClient = $eventLog->log['client_id'] == $automation->client_id;
                $isCorrectDate = $eventLogCreatedDate >= $dateStart && $eventLogCreatedDate <= $dateEnd;
                return $isCorrectClient && $isCorrectDate;
            }
        );
        return $eventLogs;
    }


    private function filterEventLogsByConditions(AutomationEmailSendStep $step, Collection $eventLogs): Collection
    {
        if ($eventLogs->isEmpty()) {
            return $eventLogs;
        }

        $leadIds = $eventLogs->pluck('log.lead.id');
        $opts = ['with' => ['user', 'status', 'tags', 'leadContactEmails']];
        $leads = $this->leadService->findByIds($leadIds, $opts);
        $enabledLeads = $this->filterLeadsByConditions($step->automationEmailSend, $leads);
        if ($step->automationEmailSend->cancel_if_sequence_was_sent) {
            $enabledLeads = $this->filterAlreadyAppliedLeadsByStep($enabledLeads, $step);
        }
        $enabledLeadIds = $enabledLeads->pluck('id')->mapWithKeys(fn ($leadId) => [$leadId => $leadId]);
        $enabledEventLogs = $eventLogs->filter(
            function (EventLog $eventLog) use ($enabledLeadIds) {
                return $enabledLeadIds->has($eventLog->log['lead']['id']);
            }
        );
        return $enabledEventLogs;
    }


    private function filterLeadsByConditions(AutomationEmailSend $automation, Collection $leads): Collection
    {
        $enabledLeads = $leads->filter(function (Lead $lead) use ($automation) {
            $leadContactEmails = $lead->leadContactEmails->filter(function ($lce) {
                return !$lce->unsubscribed && !$lce->complained && !$lce->bounced && $lce->is_valid;
            });
            if ($leadContactEmails->isEmpty()) {
                return false;
            }

            if (!$this->isLeadUserVerifiedToSendEmails($lead)) {
                return false;
            }

            $cancellingTags = $automation->cancellingTags;
            if ($cancellingTags->isNotEmpty()) {
                $leadHasCancellingTag = $cancellingTags->intersect($lead->tags)->isNotEmpty();
                if ($leadHasCancellingTag) {
                    return false;
                }
            }

            $cancellingStatusList = $automation->cancellingStatus;
            if ($cancellingStatusList->isNotEmpty()) {
                $leadHasCancellingStatus = $cancellingStatusList->contains($lead->status);
                if ($leadHasCancellingStatus) {
                    return false;
                }
            }

            if ($automation->isTagTriggered) {
                $triggeringTags = $automation->triggeringTags;
                $leadHasTriggeringTag = $triggeringTags->intersect($lead->tags)->isNotEmpty();
                if (!$leadHasTriggeringTag) {
                    return false;
                }
            }

            if ($automation->isStatusTriggered) {
                $triggeringStatusList = $automation->triggeringStatus;
                $leadHasTriggeringStatus = $triggeringStatusList->contains($lead->status);
                if (!$leadHasTriggeringStatus) {
                    return false;
                }
            }

            return true;
        });
        return $enabledLeads;
    }


    private function filterSalesByConditions(
        AutomationEmailSend $automation,
        AutomationEmailSendStep $step,
        Collection $leadSales
    ): Collection {
        $leads = $leadSales->map(fn (LeadSale $s) => $s->lead);

        $enabledLeads = $this->filterLeadsByConditions($automation, $leads);
        if ($automation->cancel_if_sequence_was_sent) {
            $enabledLeads = $this->filterAlreadyAppliedLeadsByStep($enabledLeads, $step);
        }
        $enabledSales = $leadSales->filter(function ($sale) use ($enabledLeads) {
            return $enabledLeads->where('id', $sale->lead->id)->first();
        });
        return $enabledSales;
    }


    private function filterProposalsByConditions(
        AutomationEmailSend $automation,
        AutomationEmailSendStep $step,
        Collection $proposals
    ): Collection {
        $leads = $proposals->map(function ($p) {
            return $p->lead;
        });

        $enabledLeads = $this->filterLeadsByConditions($automation, $leads);
        if ($automation->cancel_if_sequence_was_sent) {
            $enabledLeads = $this->filterAlreadyAppliedLeadsByStep($enabledLeads, $step);
        }
        $enabledProposals = $proposals->filter(function ($prop) use ($enabledLeads) {
            return $enabledLeads->where('id', $prop->lead->id)->first();
        });
        return $enabledProposals;
    }


    private function filterAlreadyAppliedLeadsByStep(Collection $leads, AutomationEmailSendStep $step): Collection
    {
        $opts = ['getRawResult' => true, 'fields' => ['id', 'lead_id']];
        $automationLogs = $this->automationLogService->findByLeadIdsAndAutomationEmailSendStep($leads, $step, $opts);
        $alreadyAppliedLeadIds = $automationLogs->pluck('lead_id')->unique()->mapWithKeys(function ($leadId) {
            return [$leadId => $leadId];
        });
        
        $enabledLeads = $leads->filter(function (Lead $lead) use ($alreadyAppliedLeadIds) {
            $wasAlreadyApplied = $alreadyAppliedLeadIds->has($lead->id);
            return !$wasAlreadyApplied;
        });
        return $enabledLeads;
    }


    private function filterAlreadyAppliedSalesByStep(Collection $leadSales, AutomationEmailSendStep $step): Collection
    {
        $automationLogs = $this
            ->automationLogService
            ->findBySalesAndAutomationEmailSendStep($leadSales, $step)
        ;
        $nonAppliedLeadSales = $leadSales->filter(function ($leadSale) use ($automationLogs) {
            return !$automationLogs->pluck('lead_sale_id')->contains($leadSale->id);
        });
        return $nonAppliedLeadSales;
    }


    private function filterAlreadyAppliedEventLogsByStep(
        Collection $eventLogs,
        AutomationEmailSendStep $step
    ): Collection {
        if ($eventLogs->isEmpty()) {
            return $eventLogs;
        }

        $leads = $eventLogs->pluck('log.lead');
        $opts = ['getRawResult' => true, 'fields' => ['id', 'event_log_ids']];
        $existentAutomationLogs = $this->automationLogService->findByLeadIdsAndAutomationEmailSendStep(
            $leads, $step, $opts
        );
        if ($existentAutomationLogs->isEmpty()) {
            return $eventLogs;
        }

        $alreadyAppliedEventLogIds = new Collection();
        foreach ($existentAutomationLogs as $existentAutomationLog) {
            $eventLogIds = json_decode($existentAutomationLog->event_log_ids, true);
            foreach ($eventLogIds as $eventLogId) {
                $alreadyAppliedEventLogIds->put($eventLogId, $eventLogId);
            }
        }
        $enabledToApplyEventLogs = $eventLogs->filter(
            function (EventLog $eventLog) use ($alreadyAppliedEventLogIds) {
                $eventLogId = $eventLog->id;
                return !($alreadyAppliedEventLogIds->has($eventLogId));
            }
        );
        return $enabledToApplyEventLogs;
    }


    private function filterAlreadyAppliedProposalsByStep(
        Collection $proposalEmails,
        AutomationEmailSendStep $step
    ): Collection {
        $automationLogs = $this->automationLogService->findByProposalsAndAutomationEmailSendStep(
            $proposalEmails, $step
        );
        $nonAppliedProposals = $proposalEmails->filter(function (Email $proposalEmail) use ($automationLogs) {
            return !$automationLogs->pluck('email_id')->contains($proposalEmail->id);
        });
        return $nonAppliedProposals;
    }


    /**************************************************
     * Automation Find Emails / Leads / Status Functions
     **************************************************/

    private function findSentProposals(AutomationEmailSend $automation, AutomationEmailSendStep $step)
    {
        $client = $automation->client;
        $dateEnd = $this->getEndDateToSearchEvents($step);
        $dateStart = $this->getStartDateToSearchEvents($automation, $step);

        $proposals = $this->emailService->findProposalsBetweenSentDatesByClient($client, $dateStart, $dateEnd);
        // Por las dudas que hayan sido borrados los leads.
        $proposals = $proposals->filter(function ($proposal) {
            return $proposal->lead;
        });
        return $proposals;
    }


    private function findLeadSales(AutomationEmailSend $automation, AutomationEmailSendStep $step): Collection
    {
        $dateEnd = $this->getEndDateToSearchEvents($step);
        $dateStart = $this->getStartDateToSearchEvents($automation, $step);
        $leadSales = $this->leadSaleService->findByClientAndDates($automation->client, $dateStart, $dateEnd);

        return $leadSales;
    }


    private function sendEmailsAndStoreLog(Collection $models, AutomationEmailSendStep $step): Collection
    {
        $sentEmails = new Collection();
        $automationLogs = new Collection();

        foreach ($models as $model) {
            $proposal = null;
            $leadSale = null;
            $lead = $model->lead;
            if ($model instanceof LeadSale) {
                $leadSale = $model;
            }
            if ($model instanceof Email) {
                $proposal = $model;
            }

            $automationLog = $this->storeAutomationLog(
                $applied = true, $lead, $step, $leadSale, $proposal, $leadEventLogs = null
            );

            // Me fijo de no duplicar envios a presupuestos:
            // Un lead puede tener N leadContactEmails, y al mandar un presupuesto, en realidad se mandan
            // 3 emails, lo cual genera 3 bucles acá, y cada bucle manda un email a TODO el lead.
            if ($proposal) {
                $lastLeadAutLog = $automationLogs->where('lead_id', $lead->id)->first();
                if ($lastLeadAutLog) {
                    $msg = 'Automation email already sent at AutomationLog ID: ' . $lastLeadAutLog->id;
                    $automationLog['exception'] = $msg;
                    $this->markAutomationLogAsNotApplied($automationLog);
                    continue;
                }
            }
            $automationLogs->push($automationLog);

            try {
                DB::beginTransaction();
                $emails = $this->sendEmailToLead($lead, $step, $automationLog);
                $sentEmails = $sentEmails->merge($emails);
                
                $this->executePostAutomationAction($lead, $step);
                
                DB::commit();
            } catch (EmailSendValidationUserNotEnabledException $e) {
                DB::rollBack();
                $automationLog['exception'] = $e->__toString();
                $automationLog = $this->markAutomationLogAsNotApplied($automationLog);
                $this->storeNotEnabledUserEmailSendNotification($automationLog);
            } catch (Throwable $e) {
                DB::rollBack();
                $automationLog['exception'] = $e->__toString();
                $automationLog = $this->markAutomationLogAsNotApplied($automationLog);
                throw $e;
            }
        }
        return $sentEmails;
    }


    public function createNewClientDefaultAfterSale(Client $client): AutomationEmailSend
    {
        $attrs = ['client_id' => $client->id];
        $aut = AutomationEmailSend::factory()->newClientDefaultAfterSale()->create($attrs);
        return $aut;
    }


    public function createNewClientDefaultAfterSentProposal(Client $client): AutomationEmailSend
    {
        $attrs = ['client_id' => $client->id];
        $aut = AutomationEmailSend::factory()->newClientDefaultAfterSentProposal()->create($attrs);
        return $aut;
    }


    /**
     * @throws EmailSendValidationUserNotEnabledException
     */
    private function sendEmailToLead(
        Lead $lead,
        AutomationEmailSendStep $step,
        AutomationLog $automationLog
    ): Collection {
        $emailScheduleParamsDTO = $this->buildEmailScheduleParamsDTO($step, $lead, $automationLog);
        $this->emailService->setRequestUser($lead->user);
        $emails = $this->emailService->scheduleToLead($lead, $emailScheduleParamsDTO);

        return $emails;
    }


    private function buildEmailScheduleParamsDTO(
        AutomationEmailSendStep $step,
        Lead $lead,
        AutomationLog $automationLog
    ): EmailScheduleParametersDTO {
        $scheduleParamsDTO = new EmailScheduleParametersDTO();
        $scheduleParamsDTO->automationLog = $automationLog;
        $scheduleParamsDTO->body = $step->sendEmailTemplate->body;
        $scheduleParamsDTO->subject = $step->sendEmailTemplate->subject;
        $scheduleParamsDTO->isProposal = $step->sendEmailTemplate->is_proposal;
        $scheduleParamsDTO->attachments = $step->sendEmailTemplate->attachments;
        $scheduleParamsDTO->sendDate = ($this->getDateNow())->format('Y-m-d\TH:i:sP');
        $scheduleParamsDTO->appCustomId = 'SYS_CID_' . $step->client->id . '_LID_' . $lead->id . '_AUT_EMAIL_SEND';

        $emailSign = $this->userService->getEmailSign($lead->user);
        if ($emailSign) {
            $scheduleParamsDTO->body = $scheduleParamsDTO->body . $emailSign;
        }
        return $scheduleParamsDTO;
    }


    private function storeNonAppliedAutomationLogsByRejectedProposals(
        Collection $rejectedProposals,
        AutomationEmailSendStep $step
    ): Collection {
        $isFullyApplied = false;
        $rejectedLeadSale = null;
        $rejectedLeadEventLogs = null;

        $nonAppliedAutomationLogs = new Collection();
        foreach ($rejectedProposals as $rejectedProposal) {
            $lead = $rejectedProposal->lead;
            $automationLog = $this->storeAutomationLog(
                $isFullyApplied, $lead, $step, $rejectedLeadSale, $rejectedProposal, $rejectedLeadEventLogs
            );
            $nonAppliedAutomationLogs->add($automationLog);
        }
        return $nonAppliedAutomationLogs;
    }


    private function storeNonAppliedAutomationLogsByRejectedLeadSales(
        Collection $rejectedLeadSales,
        AutomationEmailSendStep $step
    ): Collection {
        $isFullyApplied = false;
        $rejectedProposal = null;
        $rejectedLeadEventLogs = null;

        $nonAppliedAutomationLogs = new Collection();
        foreach ($rejectedLeadSales as $rejectedLeadSale) {
            $lead = $rejectedLeadSale->lead;
            $automationLog = $this->storeAutomationLog(
                $isFullyApplied, $lead, $step, $rejectedLeadSale, $rejectedProposal, $rejectedLeadEventLogs
            );
            $nonAppliedAutomationLogs->add($automationLog);
        }
        return $nonAppliedAutomationLogs;
    }


    private function storeAutomationLog(
        bool $isFullyApplied,
        Lead $lead,
        AutomationEmailSendStep $step,
        ?LeadSale $leadSale = null,
        ?Email $proposal = null,
        ?Collection $leadEventLogs = null
    ): AutomationLog {
        return $this->automationLogService->createAutomationEmailSendLog(
            $isFullyApplied, $lead, $step, $leadSale, $proposal, $leadEventLogs
        );
    }


    private function markAutomationLogAsNotApplied(AutomationLog $automationLog): AutomationLog
    {
        return $this->automationLogService->markAsNotApplied($automationLog);
    }


    private function storeNotEnabledUserEmailSendNotification(AutomationLog $automationLog): Notification
    {
        $notification = $this->notificationService->storeAutomationEmailSendingEmailError($automationLog);
        return $notification;
    }


    /******************************************
     * Automation Send DateTime check functions
     ******************************************/
    private function getStartDateToSearchEvents(
        AutomationEmailSend $automation,
        AutomationEmailSendStep $step
    ): DateTime {
        $client = $step->client;
        $dateTime = $this->getDateNow();
        $systemTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($client->timezone);
        $dateTime->setTimezone($clientTz);

        // Si el email se envia el MISMO día a los XX minutos
        if ($step->isToSendSameDay()) {
            $limitWindowMinutes = self::LIMIT_WINDOW_MINUTES_SAME_DAY;
            $delayMinutes = $step->send_delay_minutes + $limitWindowMinutes;
            $dateTime->modify("- {$delayMinutes} minutes")->setTimezone($systemTz);
            return $dateTime;
        }

        // if automation has the "do not send in weekends" flag
        // add 2 days only if it runs in monday to retrieve leads
        // before the weekend
        $delayDays = $step->send_delay_days;
        if ($automation->do_not_send_weekends && $this->todayIsMonday($client)) {
            $delayDays += 2;
        }
        // Que hora es para el sistema cuando para el cliente son las 00:00
        $dateTime->modify("- {$delayDays} days")->setTime(0, 0, 0)->setTimezone($systemTz);
        return $dateTime;
    }


    private function getEndDateToSearchEvents(AutomationEmailSendStep $step): DateTime
    {
        $client = $step->client;
        $dateTime = $this->getDateNow();
        $systemTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($client->timezone);
        $dateTime->setTimezone($clientTz);

        // Si el email se envia el MISMO día a los XX minutos
        if ($step->isToSendSameDay()) {
            $delayMinutes = $step->send_delay_minutes;
            $dateTime->modify("- {$delayMinutes} minutes")->setTimezone($systemTz);
            return $dateTime;
        }

        // Que hora es para el sistema cuando para el cliente son las 23:59:59
        $dateTime->modify("- {$step->send_delay_days} days")->setTime(23, 59, 59)->setTimezone($systemTz);
        return $dateTime;
    }


    private function filterProposalsEnabledToSend(Collection $proposals, AutomationEmailSendStep $step): Collection
    {
        $enabledProposals = new Collection();
        foreach ($proposals as $proposal) {
            $isTimeToSend = $this->isTimeToSendStepEmail($proposal, $step);
            if ($isTimeToSend) {
                $enabledProposals->push($proposal);
            }
        }
        return $enabledProposals;
    }


    private function filterSalesEnabledToSend(Collection $leadSales, AutomationEmailSendStep $step): Collection
    {
        $enabledSales = new Collection();
        foreach ($leadSales as $leadSale) {
            $isTimeToSend = $this->isTimeToSendStepEmail($leadSale, $step);
            if ($isTimeToSend) {
                $enabledSales->push($leadSale);
            }
        }
        return $enabledSales;
    }


    private function filterEventLogsEnabledToSend(Collection $eventLogs, AutomationEmailSendStep $step): Collection
    {
        if ($eventLogs->isEmpty()) {
            return $eventLogs;
        }

        $enabledEventLogs = new Collection();
        $leadIds = $eventLogs->pluck('log.lead.id');
        $leads = $this->leadService->findByIds($leadIds, ['getRawResult' => true, 'fields' => ['id']]);
        $leads = $leads->mapWithKeys(fn ($l) => [$l->id => $l->id]);

        $enabledEventLogs = $eventLogs->filter(
            function (EventLog $eventLog) use ($step, $leads) {
                $isTimeToSend = $this->isTimeToSendStepEmail($eventLog, $step);
                if (!$isTimeToSend) {
                    return false;
                }
                $leadExists = $leads->has($eventLog->log['lead']['id']);
                return $leadExists;
            }
        );
        return $enabledEventLogs;
    }


    private function isTimeToSendStepEmail(object $object, AutomationEmailSendStep $step): bool
    {
        $dateNow = $this->getDateNow();

        // check if the object is an EventLog or a model
        if ($object instanceof EventLog) {
            $stepSendDate = $this->getStepSendDate($object->createdAt, $step);
        } elseif ($object instanceof Email) {
            $stepSendDate = $this->getStepSendDate($object->sent_date, $step);
        } elseif ($object instanceof LeadSale) {
            $stepSendDate = $this->getStepSendDate($object->sale_date, $step);
        }

        // Si el email se envia el MISMO día a los XX minutos
        if ($step->isToSendSameDay()) {
            $limitWindowMinutes = self::LIMIT_WINDOW_MINUTES_SAME_DAY;
            $maxStepSendDate = (clone($stepSendDate))->modify("+{$limitWindowMinutes} minutes");
        } else {
            $limitWindowHours = self::LIMIT_WINDOW_HOURS;
            $maxStepSendDate = (clone($stepSendDate))->modify("+{$limitWindowHours} hours");
        }

        $sendDateReached = $dateNow >= $stepSendDate;
        $sendWindowExceeded = $dateNow >= $maxStepSendDate;
        return ($sendDateReached && !$sendWindowExceeded);
    }


    public function isInHourToApply(AutomationEmailSendStep $step): bool
    {
        if (!$step->send_hour) {
            return true; // No aplica si no tiene hora.
        }
        $dateNow = $this->getDateNow();
        $hourNow = (int) $dateNow->format('H');
        $hourArr = explode(':', $step->send_hour);
        $hourToApply = (int) $hourArr[0];
        $hoursAbsoluteDiff = absoluteHoursDiff($hourNow, $hourToApply);
        if ($hoursAbsoluteDiff <= self::LIMIT_WINDOW_HOURS) {
            return true;
        }
        return false;
    }


    private function getStepSendDate(DateTime $eventCreationDate, AutomationEmailSendStep $step): DateTime
    {
        // Si el email se envia el MISMO día a los XX minutos
        if ($step->isToSendSameDay()) {
            $sendDate = (clone $eventCreationDate)->modify('+ ' . $step->send_delay_minutes . ' minutes');
            return $sendDate;
        }

        $systemTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($step->client->timezone);

        $stepSendDate = (clone $eventCreationDate);
        // Uso el TZ del cliente para saber que día era para el cliente, y luego sumarle XX días.
        $stepSendDate->setTimezone($clientTz)->setTime(0, 0, 0);
        $stepSendDate->modify('+ ' . $step->send_delay_days . ' days');

        // Si tuvo que haber sido enviado un fin de semana, y hoy es lunes
        // la fecha de envío entonces es el proximo lunes a la fecha original de envío.
        $stepSendDateIsWeekend = $this->dateIsWeekend($stepSendDate);
        $doNotSendWeekends = $step->automationEmailSend->do_not_send_weekends;
        $todayIsMonday = $this->todayIsMonday($step->automationEmailSend->client);
        if ($doNotSendWeekends && $stepSendDateIsWeekend && $todayIsMonday) {
            $stepSendDate = $stepSendDate->modify('next monday');
        }

        // Uso el TZ del cliente para saber la hora en su timezone.
        $hourArr = explode(':', $step->send_hour);
        $clientStepSendHour = ($this->getDateNow())->setTime((int) $hourArr[0], (int) $hourArr[1], 0);
        $clientStepSendHour = $clientStepSendHour->setTimezone($clientTz);
        
        $clientHourArr = explode(':', $clientStepSendHour->format('H:i'));
        $stepSendDate->setTime((int) $clientHourArr[0], (int) $clientHourArr[1], 0);

        // Una vez que tengo armada la fecha/hora en TZ del cliente, la paso a UTC0.
        $stepSendDate->setTimezone($systemTz);

        return $stepSendDate;
    }


    public function isWeekendAndCanNotRun(AutomationEmailSend $automation): bool
    {
        $client = $automation->client;
        // 6 -> saturday, 0 -> sunday
        $dayOfWeek = (int) ($this->getDateNow())->setTimezone(new DateTimeZone($client->timezone))->format('w');
        if ($automation->do_not_send_weekends && ($dayOfWeek == 6 || $dayOfWeek == 0)) {
            return true;
        }
        return false;
    }


    private function todayIsMonday(Client $client): bool
    {
        return (int) ($this->getDateNow())->setTimezone(new DateTimeZone($client->timezone))->format('w') == 1;
    }


    private function dateIsWeekend(DateTime $date): bool
    {
        return ((int) $date->format('w')) == 0 || ((int) $date->format('w')) == 6;
    }


    private function isLeadUserVerifiedToSendEmails(Lead $lead): bool
    {
        $user = $lead->user;
        return ($user->email_is_verified && $user->email_from_address && $user->email_from_name);
    }


    private function executePostAutomationAction(Lead $lead, AutomationEmailSendStep $step): void
    {
        $this->addTagsToLead($lead, $step);
        $this->assignStatusToLead($lead, $step);
    }


    private function addTagsToLead(Lead $lead, AutomationEmailSendStep $step): void
    {
        $tagsToAdd = $step->tagsToAdd;
        if ($tagsToAdd && $tagsToAdd->isNotEmpty()) {
            $leadTags = $lead->tags;
            $tagsToAdd = $tagsToAdd->filter(function (Tag $tagToAdd) use ($leadTags) {
                $alreadyAssignedTag = $leadTags->where('id', $tagToAdd->id)->first();
                return $alreadyAssignedTag ? false : true;
            });
            if ($tagsToAdd->isNotEmpty()) {
                $allTags = $leadTags->merge($tagsToAdd);
                if ($allTags) {
                    resolve(ActionsLeadService::class)->setLeadTags($lead, $allTags);
                }
            }
        }
    }


    private function assignStatusToLead(Lead $lead, AutomationEmailSendStep $step): void
    {
        $statusToAdd = $step->statusToAdd;
        if ($statusToAdd) {
            if ($lead->status_id != $statusToAdd->id) {
                resolve(ActionsLeadService::class)->changeStatus($lead, $statusToAdd);
            }
        }
    }


    // Unifico esto acá para poder hacer pruebas cambiando la "fecha actual"
    private function getDateNow(): DateTime
    {
        // return environmentIsNotProduction() ? new DateTime('2023-12-18 13:14:00') : new DateTime('now');
        return new DateTime('now');
    }

}
