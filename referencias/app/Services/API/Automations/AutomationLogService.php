<?php

namespace App\Services\API\Automations;

use DateTime;
use Throwable;
use Exception;
use App\Models\Lead;
use App\Models\User;
use App\Models\Task;
use App\Models\Email;
use App\Models\LeadSale;
use Illuminate\Http\Request;
use App\Models\AutomationLog;
use App\Models\AutomationTask;
use App\Models\AutomationNewLead;
use Illuminate\Support\Collection;
use App\Models\AutomationEmailSend;
use App\Models\AutomationEmailSendStep;
use App\Models\AutomationProposalResendRule;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Sort\SortCriteria;
use App\Models\AutomationProposalInteractionRule;
use App\Models\AutomationProposalModifyLeadAfterSendRule;
use App\Repositories\Automations\AutomationLogRepository;
use App\Repositories\Criteria\Sort\AutomationLog\SortByCreated;
use App\Repositories\Criteria\Filter\AutomationLog\UserIdCriteria;
use App\Repositories\Criteria\Filter\AutomationLog\TypeLogCriteria;
use App\Repositories\Criteria\Filter\AutomationLog\DateEndCriteria;
use App\Repositories\Criteria\Filter\AutomationLog\DateStartCriteria;


class AutomationLogService
{

    use GetClientFromRequest;


    public function __construct(AutomationLogRepository $automationLogRepository)
    {
        $this->automationLogRepository = $automationLogRepository;
    }


    public function list(array $opts): LengthAwarePaginator
    {
        $client = $this->getClient();
        $repoOpts = [
            'page' => $opts['page'] ?? 1,
            'with' => $opts['with'] ?? [],
            'limit' => $opts['limit'] ?? 20,
            'order' => $this->getSortCriteriasByName($opts['sort'] ?? ''),
            'filters' => $this->getFilterCriteriasByName($opts['filters'] ?? []),
        ];
        return $this->automationLogRepository->listPaginated($client, $repoOpts);
    }


    public function markAsNotApplied(AutomationLog $automationLog): AutomationLog
    {
        return $this->automationLogRepository->update($automationLog, ['is_fully_applied' => false]);
    }


    public function createAutomationNewLeadLog(
        bool $applied,
        Lead $lead,
        AutomationNewLead $aut,
        ?int $assignedUserId = null
    ): AutomationLog {
        $data = [
            'lead_id' => $lead->id,
            'is_fully_applied' => $applied,
            'client_id' => $lead->client_id,
            'automation_new_lead_id' => $aut->id,
            'automation_new_lead_assigned_user_id' => $assignedUserId,
        ];
        return $this->automationLogRepository->create($data);
    }


    public function createAutomationEmailSendLog(
        bool $applied,
        Lead $lead,
        AutomationEmailSendStep $step,
        ?LeadSale $leadSale = null,
        ?Email $proposal = null,
        ?Collection $leadEventLogs = null
    ): AutomationLog {
        $data = [
            'lead_id' => $lead->id,
            'is_fully_applied' => $applied,
            'client_id' => $lead->client_id,
            'automation_email_send_step_id' => $step->id,
            'automation_email_send_id' => $step->automation_email_send_id
        ];
        if ($leadSale) {
            $data['lead_sale_id'] = $leadSale->id;
        }
        if ($proposal) {
            $data['email_id'] = $proposal->id;
        }
        if ($leadEventLogs) {
            $data['event_log_ids'] = $leadEventLogs->pluck('id')->toArray();
        }
        return $this->automationLogRepository->create($data);
    }


    public function createAutomationProposalResendLog(
        bool $applied,
        Email $email,
        AutomationProposalResendRule $rule
    ): AutomationLog {
        $data = [
            'email_id' => $email->id,
            'lead_id' => $email->lead->id,
            'is_fully_applied' => $applied,
            'client_id' => $email->lead->client_id,
            'automation_proposal_id' => $rule->automation_proposal_id,
            'automation_proposal_resend_rule_id' => $rule->id
        ];
        return $this->automationLogRepository->create($data);
    }


    public function createAutomationProposalModifyLeadAfterSendLog(
        Lead $lead,
        AutomationProposalModifyLeadAfterSendRule $rule
    ): AutomationLog {
        $data = [
            'lead_id' => $lead->id,
            'client_id' => $lead->client_id,
            'automation_proposal_id' => $rule->automation_proposal_id,
            'automation_proposal_modify_lead_after_send_rule_id' => $rule->id
        ];

        return $this->automationLogRepository->create($data);
    }


    public function createAutomationProposalInteractionLog(
        Email $openedEmail,
        AutomationProposalInteractionRule $rule,
        Exception | null $exception = null
    ): AutomationLog {
        $applied = $exception ? false : true;
        $data = [
            'email_id' => $openedEmail->id,
            'is_fully_applied' => $applied,
            'lead_id' => $openedEmail->lead->id,
            'exception' => $exception?->getMessage(),
            'client_id' => $openedEmail->lead->client_id,
            'automation_proposal_interaction_rule_id' => $rule->id,
            'automation_proposal_id' => $rule->automation_proposal_id,
        ];
        return $this->automationLogRepository->create($data);
    }


    public function createAutomationTaskAfterSaleLog(
        LeadSale $triggeringLeadSale,
        AutomationTask $automationTask,
        Exception | null $exception = null
    ): AutomationLog {
        $applied = $exception ? false : true;
        $data = [
            'is_fully_applied' => $applied,
            'exception' => $exception?->getMessage(),
            'lead_sale_id' => $triggeringLeadSale->id,
            'client_id' => $automationTask->client_id,
            'lead_id' => $triggeringLeadSale->lead->id,
            'automation_task_id' => $automationTask->id,
        ];
        return $this->automationLogRepository->create($data);
    }


    public function createAutomationTaskAfterTaskExpirationLog(
        Task $triggeringTask,
        AutomationTask $automationTask,
        Exception | null $exception = null
    ): AutomationLog {
        $applied = $exception ? false : true;
        $data = [
            'is_fully_applied' => $applied,
            'task_id' => $triggeringTask->id,
            'lead_id' => $triggeringTask->lead_id,
            'exception' => $exception?->getMessage(),
            'client_id' => $automationTask->client_id,
            'automation_task_id' => $automationTask->id,
        ];
        return $this->automationLogRepository->create($data);
    }


    // @param $leadEventLogs -> Collection<EventLog>
    public function createAutomationTaskAfterTagStatusChangeLog(
        Collection $leadEventLogs,
        AutomationTask $automationTask,
        Exception | null $exception = null
    ): AutomationLog {
        $applied = $exception ? false : true;
        $data = [
            'is_fully_applied' => $applied,
            'exception' => $exception?->getMessage(),
            'automation_task_id' => $automationTask->id,
            'event_log_ids' => $leadEventLogs->pluck('id'),
            'lead_id' => $leadEventLogs->first()->log['lead']['id'],
            'client_id' => $leadEventLogs->first()->log['client_id'],
        ];
        return $this->automationLogRepository->create($data);
    }


    public function findLastOneByAutomationTaskAndLeadSale(
        AutomationTask $automationTask,
        LeadSale $triggeringLeadSale,
    ): ?AutomationLog {
        return $this->automationLogRepository->findLastOneByAutomationTaskAndLeadSale(
            $triggeringLeadSale, $automationTask
        );
    }


    public function findLastOneByAutomationTaskAndTriggeringTask(
        AutomationTask $automationTask,
        Task $triggeringTask,
    ): ?AutomationLog {
        return $this->automationLogRepository->findLastOneByAutomationTaskAndTriggeringTask(
            $triggeringTask, $automationTask
        );
    }


    public function findAppliedByAutomationTaskBetweenDates(
        AutomationTask $automationTask,
        DateTime $dateStart,
        DateTime $dateEnd,
    ): Collection {
        return $this->automationLogRepository->findAppliedByAutomationTaskBetweenDates(
            $automationTask, $dateStart, $dateEnd
        );
    }


    public function findLastOneByAutomationTaskAndLeadId(AutomationTask $automationTask, int $leadId): ?AutomationLog
    {
        return $this->automationLogRepository->findLastOneByAutomationTaskAndLeadId($automationTask, $leadId);
    }


    public function findOneByLeadAndAutomationNewLead(Lead $lead, AutomationNewLead $aut): ?AutomationLog
    {
        return $this->automationLogRepository->findOneByLeadAndAutomationNewLead($lead, $aut);
    }


    public function findLastOneByAutomationNewLead(AutomationNewLead $automationNewLead): ?AutomationLog
    {
        return $this->automationLogRepository->findLastOneByAutomationNewLead($automationNewLead);
    }
    

    public function findByAutomationNewLead(AutomationNewLead $automationNewLead)
    {
        return $this->automationLogRepository->findByAutomationNewLead($automationNewLead);
    }


    public function findByLeadIdsAndAutomationEmailSendStep(
        Collection $leads,
        AutomationEmailSendStep $step,
        array $opts = []
    ): Collection {
        return $this->automationLogRepository->findByLeadIdsAndAutomationEmailSendStep($leads, $step, $opts);
    }


    public function findBySalesAndAutomationEmailSendStep(
        Collection $leadSales,
        AutomationEmailSendStep $step
    ): Collection {
        return $this->automationLogRepository->findBySalesAndAutomationEmailSendStep($leadSales, $step);
    }


    public function findByProposalsAndAutomationEmailSendStep(
        Collection $proposalEmails,
        AutomationEmailSendStep $step
    ): Collection {
        return $this->automationLogRepository->findByProposalsAndAutomationEmailSendStep($proposalEmails, $step);
    }


    public function findByAutomationEmailSend(AutomationEmailSend $automationEmailSend, array $opts = []): Collection
    {
        return $this->automationLogRepository->findByAutomationEmailSend($automationEmailSend, $opts);
    }


    public function findOneByAutomationTask(AutomationTask $automationTask): ?AutomationLog
    {
        return $this->automationLogRepository->findOneByAutomationTask($automationTask);
    }


    public function findOneByAutomationEmailSendStep(AutomationEmailSendStep $automationEmailSendStep): ?AutomationLog
    {
        return $this->automationLogRepository->findOneByAutomationEmailSendStep($automationEmailSendStep);
    }


    public function findOneByLeadAndEmailAndAutomationProposalInteractionRule(
        Lead $lead,
        Email $email,
        AutomationProposalInteractionRule $rule
    ): ?AutomationLog {
        return $this
            ->automationLogRepository
            ->findOneByLeadAndEmailAndAutomationProposalInteractionRule($lead, $email, $rule)
        ;
    }


    public function findByAutomationProposalInteractionRule(
        AutomationProposalInteractionRule $rule
    ): Collection {

        return $this->automationLogRepository->findByAutomationProposalInteractionRule($rule);
    }


    public function findOneByLeadAndEmailAndAutomationProposalResendRule(
        Lead $lead,
        Email $email,
        AutomationProposalResendRule $rule
    ): ?AutomationLog {
        return $this
            ->automationLogRepository
            ->findOneByLeadAndEmailAndAutomationProposalResendRule($lead, $email, $rule)
        ;
    }


    public function findByAutomationProposalResendRule(
        AutomationProposalResendRule $rule
    ): Collection {
        return $this->automationLogRepository->findByAutomationProposalResendRule($rule);
    }


    public function findLastOneByAutomationProposalResendRule(
        AutomationProposalResendRule $rule
    ): ?AutomationLog {
        return $this->automationLogRepository->findLastOneByAutomationProposalResendRule($rule);
    }


    public function findOneByLeadAndAutomationProposalModifyLeadAfterSend(
        Lead $lead,
        AutomationProposalModifyLeadAfterSendRule $rule
    ): ?AutomationLog {
        return $this
            ->automationLogRepository
            ->findOneByLeadAndAutomationProposalModifyLeadAfterSend($lead, $rule)
        ;
    }


    public function findByAutomationProposalModifyLeadAfterSendRule(
        AutomationProposalModifyLeadAfterSendRule $rule
    ): Collection {
        return $this
            ->automationLogRepository
            ->findByAutomationProposalModifyLeadAfterSendRule($rule)
        ;
    }
    

    public function findLastOneByAutomationProposalModifyLeadAfterSendRule(
        AutomationProposalModifyLeadAfterSendRule $rule
    ): ?AutomationLog {
        return $this
            ->automationLogRepository
            ->findLastOneByAutomationProposalModifyLeadAfterSendRule($rule)
        ;
    }


    private function getFilterCriteriasByName($filters)
    {
        $nfilters = [];
        $criterias = [
            'type' => TypeLogCriteria::class,
            'user_id' => UserIdCriteria::class,
            'date_end' => DateEndCriteria::class,
            'date_start' => DateStartCriteria::class,
        ];
        foreach ($filters as $key => $value) {
            if (in_array($key, array_keys($criterias))) {
                $nfilters[$key] = new $criterias[$key]($value);
            } else {
                $nfilters[$key] = $value;
            }
        }
        return $nfilters;
    }


    private function getSortCriteriasByName(string $sortsName): SortCriteria | string
    {
        $sortTypes = ['date_asc' => new SortByCreated('asc'), 'date_desc' => new SortByCreated('desc')];
        return $sortTypes[$sortsName] ?? '';
    }

}
