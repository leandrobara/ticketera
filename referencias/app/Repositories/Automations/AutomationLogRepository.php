<?php

namespace App\Repositories\Automations;

use DateTime;
use App\Models\Lead;
use App\Models\Task;
use App\Models\Email;
use App\Models\Client;
use App\Models\LeadSale;
use App\Models\AutomationLog;
use App\Models\AutomationTask;
use App\Models\AutomationNewLead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\AutomationEmailSend;
use App\Models\AutomationEmailSendStep;
use App\Models\AutomationProposalResendRule;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Sort\SortCriteria;
use App\Models\AutomationProposalInteractionRule;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;
use App\Models\AutomationProposalModifyLeadAfterSendRule;


class AutomationLogRepository
{

    public function create($data): AutomationLog
    {
        $log = new AutomationLog($data);
        $log->saveOrFail();
        return $log->fresh();
    }


    public function listPaginated(Client $client, array $options = []): LengthAwarePaginator
    {
        $limit = $options['limit'] ?? 20;
        $order = $options['order'] ?? null;
        $pageNumber = $options['page'] ?? 1;
        $filters = $options['filters'] ?? [];
        $relationshipsToEagerLoad = $options['with'] ?? [];
        $queryBuilder = AutomationLog::withTrashed()->where('client_id', $client->id);
        //$queryBuilder->where('is_fully_applied', true);
        if ($relationshipsToEagerLoad) {
            $queryBuilder->with($relationshipsToEagerLoad);
        }
        $queryBuilder = $this->applyFilters($queryBuilder, $filters);

        if ($order) {
            if (is_a($order, SortCriteria::class)) {
                $queryBuilder = $order->applySort($queryBuilder);
            } else {
                $queryBuilder->orderByRaw($order);
            }
        }
        $result = $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);
        return $result;
    }


    public function update(AutomationLog $automationLog, array $data): AutomationLog
    {
        $automationLog->fill($data);
        $automationLog->saveOrFail();
        return $automationLog->fresh();
    }


    public function findOneByLeadAndAutomationNewLead(Lead $lead, AutomationNewLead $automationNewLead): ?AutomationLog
    {
        return AutomationLog::where([
            'lead_id' => $lead->id,
            'client_id' => $automationNewLead->client_id,
            'automation_new_lead_id' => $automationNewLead->id
        ])->first();
    }


    public function findLastOneByAutomationTaskAndLeadSale(
        LeadSale $triggeringLeadSale,
        AutomationTask $automationTask
    ): ?AutomationLog {
        $filters = [
            'lead_sale_id' => $triggeringLeadSale->id,
            'lead_id' => $triggeringLeadSale->lead_id,
            'automation_task_id' => $automationTask->id,
        ];
        return AutomationLog::where($filters)
            ->from(DB::raw('AutomationsLogs USE INDEX(automationslogs_lead_id_index)'))
            ->orderBy('id', 'desc')
            ->limit(1)
            ->first()
        ;
    }


    public function findLastOneByAutomationTaskAndTriggeringTask(
        Task $triggeringTask,
        AutomationTask $automationTask
    ): ?AutomationLog {
        $filters = [
            'task_id' => $triggeringTask->id,
            'lead_id' => $triggeringTask->lead_id,
            'automation_task_id' => $automationTask->id,
        ];
        return AutomationLog::where($filters)
            ->from(DB::raw('AutomationsLogs USE INDEX(automationslogs_lead_id_index)'))
            ->orderBy('id', 'desc')
            ->limit(1)
            ->first()
        ;
    }


    public function findAppliedByAutomationTaskBetweenDates(
        AutomationTask $automationTask,
        DateTime $dateStart,
        DateTime $dateEnd,
    ): Collection {
        return AutomationLog::where('is_fully_applied', true)
            ->where('automation_task_id', $automationTask->id)
            ->where('created_at', '>=', $dateStart)
            ->where('created_at', '<=', $dateEnd)
            ->orderBy('id', 'desc')
            ->get()
        ;
    }


    public function findLastOneByAutomationTaskAndLeadId(AutomationTask $automationTask, int $leadId): ?AutomationLog
    {
        return AutomationLog::where('automation_task_id', $automationTask->id)
            ->where('lead_id', '=', $leadId)
            ->orderBy('id', 'desc')
            ->limit(1)
            ->first()
        ;
    }


    public function findByAutomationNewLead(AutomationNewLead $automationNewLead): ?Collection
    {
        return AutomationLog::where(['automation_new_lead_id' => $automationNewLead->id])->get();
    }


    public function findOneByAutomationTask(AutomationTask $automationTask): ?AutomationLog
    {
        return AutomationLog::where('automation_task_id', $automationTask->id)->limit(1)->first();
    }


    public function findLastOneByAutomationNewLead(AutomationNewLead $automationNewLead): ?AutomationLog
    {
        return AutomationLog::where(['automation_new_lead_id' => $automationNewLead->id])
            ->orderBy('id', 'desc')
            ->limit(1)
            ->first()
        ;
    }


    public function findByLeadIdsAndAutomationEmailSendStep(
        Collection $leads,
        AutomationEmailSendStep $step,
        array $opts = []
    ): Collection {
        $fields = $opts['fields'] ?? [];
        $getRawResult = $opts['getRawResult'] ?? false;

        $queryBuilder = AutomationLog::query();
        if ($getRawResult) {
            $queryBuilder = DB::table('AutomationsLogs')->whereNull('deleted_at');
        }

        $queryBuilder
            ->where('client_id', $step->client_id)
            ->whereIn('lead_id', $leads->pluck('id'))
            ->where('automation_email_send_step_id', $step->id)
            ->where('automation_email_send_id', $step->automation_email_send_id)
        ;
        if ($fields) {
            $queryBuilder->select($fields);
        }
        return $queryBuilder->get();
    }


    public function findBySalesAndAutomationEmailSendStep(
        Collection $leadSales,
        AutomationEmailSendStep $step
    ): Collection {
        $leadSalesIds = $leadSales->pluck('id');
        $leadIds = $leadSales->map(function ($s) {
            return $s->lead;
        })->pluck('id');

        return AutomationLog::where([
            'client_id' => $step->client_id,
            'automation_email_send_step_id' => $step->id,
            'automation_email_send_id' => $step->automation_email_send_id,
        ])
        ->whereIn('lead_id', $leadIds)
        ->whereIn('lead_sale_id', $leadSalesIds)
        ->get();
    }


    public function findByProposalsAndAutomationEmailSendStep(
        Collection $proposalEmails,
        AutomationEmailSendStep $step
    ): Collection {
        if ($proposalEmails->isEmpty()) {
            return new Collection();
        }

        $proposalEmailsIds = $proposalEmails->pluck('id');
        $leadIds = $proposalEmails->map(function (Email $proposalEmail) {
            return $proposalEmail->lead;
        })->pluck('id');

        return AutomationLog::where([
            'client_id' => $step->client_id,
            'automation_email_send_step_id' => $step->id,
            'automation_email_send_id' => $step->automation_email_send_id,
        ])
        ->whereIn('lead_id', $leadIds)
        ->whereIn('email_id', $proposalEmailsIds)
        ->get();
    }


    public function findOneByLeadAndEmailAndAutomationProposalInteractionRule(
        Lead $lead,
        Email $email,
        AutomationProposalInteractionRule $rule
    ): ?AutomationLog {
        return AutomationLog::where([
            'client_id' => $rule->client_id,
            'email_id' => $email->id,
            'lead_id' => $lead->id,
            'automation_proposal_interaction_rule_id' => $rule->id,
            'automation_email_send_id' => $rule->automation_email_send_id
        ])->first();
    }


    public function findByAutomationEmailSend(AutomationEmailSend $automationEmailSend, array $opts = []): Collection
    {
        $builder = AutomationLog::where('automation_email_send_id', $automationEmailSend->id);

        $limit = $opts['limit'] ?? 999999;
        $builder->limit($limit);

        return $builder->get();
    }


    public function findOneByAutomationEmailSendStep(AutomationEmailSendStep $automationEmailSendStep): ?AutomationLog
    {
        return AutomationLog::where('automation_email_send_step_id', $automationEmailSendStep->id)
            ->where('automation_email_send_id', $automationEmailSendStep->automation_email_send_id)
            ->where('client_id', $automationEmailSendStep->client_id)
            ->first()
        ;
    }


    public function findByAutomationProposalInteractionRule(
        AutomationProposalInteractionRule $rule
    ): ?Collection {
        return AutomationLog::where('automation_proposal_interaction_rule_id', $rule->id)->get();
    }


    public function findOneByLeadAndEmailAndAutomationProposalResendRule(
        Lead $lead,
        Email $email,
        AutomationProposalResendRule $rule
    ): ?AutomationLog {
        return AutomationLog::where([
            'client_id' => $rule->client_id,
            'email_id' => $email->id,
            'lead_id' => $lead->id,
            'automation_proposal_resend_rule_id' => $rule->id,
        ])->first();
    }


    public function findByAutomationProposalResendRule(AutomationProposalResendRule $rule): ?Collection
    {
        return AutomationLog::where('automation_proposal_resend_rule_id', $rule->id)->get();
    }


    public function findLastOneByAutomationProposalResendRule(AutomationProposalResendRule $rule): ?AutomationLog
    {
        return AutomationLog::where('automation_proposal_resend_rule_id', $rule->id)
            ->orderBy('id', 'desc')
            ->limit(1)
            ->first()
        ;
    }


    public function findOneByLeadAndAutomationProposalModifyLeadAfterSend(
        Lead $lead,
        AutomationProposalModifyLeadAfterSendRule $rule
    ): ?AutomationLog {
        return AutomationLog::where([
            'lead_id' => $lead->id,
            'client_id' => $rule->client_id,
            'automation_proposal_modify_lead_after_send_rule_id' => $rule->id,
        ])->first();
    }


    public function findByAutomationProposalModifyLeadAfterSendRule(
        AutomationProposalModifyLeadAfterSendRule $rule
    ): Collection {
        return AutomationLog::where('automation_proposal_modify_lead_after_send_rule_id', $rule->id)->get();
    }


    public function findLastOneByAutomationProposalModifyLeadAfterSendRule(
        AutomationProposalModifyLeadAfterSendRule $rule
    ): ?AutomationLog {
        return AutomationLog::where('automation_proposal_modify_lead_after_send_rule_id', $rule->id)
            ->orderBy('id', 'desc')
            ->limit(1)
            ->first()
        ;
    }


    protected function applyFilters(object $queryBuilder, array $filters): object
    {
        foreach ($filters as $key => $value) {
            if (isset($filters[$key])) {
                if (is_array($value)) {
                    $queryBuilder->whereIn($key, $value);
                } elseif ($filters[$key] instanceof SQLFilterCriteria) {
                    $queryBuilder = $filters[$key]->filterSQLQuery($queryBuilder);
                } else {
                    $queryBuilder->where($key, $value);
                }
            }
        }
        return $queryBuilder;
    }

}
