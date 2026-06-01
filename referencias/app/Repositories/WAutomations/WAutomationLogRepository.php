<?php

namespace App\Repositories\WAutomations;

use DateTime;
use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use App\Models\LeadSale;
use App\Models\WAutomationLog;
use App\Models\WhatsAppSending;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\WAutomationSequence;
use App\Models\WAutomationAfterSend;
use App\Models\WhatsAppSendingMessage;
use App\Models\WAutomationSequenceStep;
use App\Models\WAutomationProposalResendRule;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Sort\SortCriteria;
use App\Models\AutomationProposalInteractionRule;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;
use App\Models\WAutomationProposalModifyLeadAfterSendRule;


class WAutomationLogRepository
{

    public function create($data): WAutomationLog
    {
        $log = new WAutomationLog($data);
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
        
        $queryBuilder = WAutomationLog::withTrashed()->where('client_id', $client->id);
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
        // DB::enableQueryLog();
        $result = $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);
        // dd(DB::getQueryLog());
        return $result;
    }


    public function update(WAutomationLog $automationLog, array $data): WAutomationLog
    {
        $automationLog->fill($data);
        $automationLog->saveOrFail();
        return $automationLog->fresh();
    }


    public function findOneByWAutomationAfterSend(
        WAutomationAfterSend $wAutomationAfterSend
    ): ?WAutomationLog {
        return WAutomationLog::where('wautomation_after_send_id', $wAutomationAfterSend->id)
            ->where('client_id', $wAutomationAfterSend->client_id)
            ->from(DB::raw('WAutomationsLogs USE INDEX(wautlogs_wautomation_after_send_id_index)'))
            ->limit(1)
            ->first()
        ;
    }


    public function findOneByWAutomationSequence(WAutomationSequence $wAutomationSequence): ?WAutomationLog
    {
        $log = WAutomationLog::where('wautomation_sequence_id', $wAutomationSequence->id)
            ->where('client_id', $wAutomationSequence->client_id)
            ->from(DB::raw('WAutomationsLogs USE INDEX(wautlogs_wautomation_sequence_id_index)'))
            ->limit(1)
            ->first()
        ;
        return $log;
    }


    public function findOneByWAutomationSequenceStep(WAutomationSequenceStep $wAutomationSequenceStep): ?WAutomationLog
    {
        $log = WAutomationLog::where('wautomation_sequence_id', $wAutomationSequenceStep->wautomation_sequence_id)
            ->where('wautomation_sequence_step_id', $wAutomationSequenceStep->id)
            ->where('client_id', $wAutomationSequenceStep->client_id)
            ->from(DB::raw('WAutomationsLogs USE INDEX(wautlogs_wautomation_sequence_id_index)'))
            ->limit(1)
            ->first()
        ;
        return $log;
    }


    public function findOneAfterSendByWhatsAppSendingMessage(WhatsAppSendingMessage $wapSendingMsg): ?WAutomationLog
    {
        return WAutomationLog::where('whatsapp_sending_message_id', $wapSendingMsg->id)
            ->whereNotNull('wautomation_after_send_id')
            ->from(DB::raw('WAutomationsLogs USE INDEX(wautlogs_whatsapp_sending_message_id_index)'))
            ->limit(1)
            ->first()
        ;
    }


    public function findOneAfterSendByLeadId(int $leadId): ?WAutomationLog
    {
        return WAutomationLog::where('lead_id', $leadId)
            ->whereNotNull('wautomation_after_send_id')
            ->from(DB::raw('WAutomationsLogs USE INDEX(wautlogs_lead_id_index)'))
            ->limit(1)
            ->first()
        ;
    }


    public function findOneByWAutomationProposalResendRule(WAutomationProposalResendRule $rule): ?WAutomationLog
    {
        return WAutomationLog::where('wautomation_proposal_resend_rule_id', $rule->id)
            ->limit(1)
            ->first()
        ;
    }


    public function findOneByWAutomationProposalModifyLeadAfterSendRule(
        WAutomationProposalModifyLeadAfterSendRule $rule
    ): ?WAutomationLog {
        return WAutomationLog::where('wautomation_proposal_modify_lead_after_send_rule_id', $rule->id)
            ->limit(1)
            ->first()
        ;
    }


    public function findOneByLeadAndWAutomationProposalModifyLeadAfterSendRule(
        Lead $lead,
        WAutomationProposalModifyLeadAfterSendRule $rule
    ): ?WAutomationLog {
        // Se aplica 1 sola vez POR LEAD, no por mensaje.
        $filters = ['lead_id' => $lead->id, 'wautomation_proposal_modify_lead_after_send_rule_id' => $rule->id];
        return WAutomationLog::where($filters)
            ->from(DB::raw('WAutomationsLogs USE INDEX(wautlogs_lead_id_index)'))
            ->limit(1)
            ->first()
        ;
    }


    public function findOneByWhatsAppSendingMessageAndWAutomationProposalResendRule(
        WhatsAppSendingMessage $whatsAppSendingMessage,
        WAutomationProposalResendRule $rule
    ): ?WAutomationLog {
        $filters = [
            'wautomation_proposal_resend_rule_id' => $rule->id,
            'whatsapp_sending_message_id' => $whatsAppSendingMessage->id,
        ];
        return WAutomationLog::where($filters)
            ->from(DB::raw('WAutomationsLogs USE INDEX(wautlogs_whatsapp_sending_message_id_index)'))
            ->limit(1)
            ->first()
        ;
    }


    public function findOneByWhatsAppSendingMessageAndWAutomationSequenceStep(
        WhatsAppSendingMessage $wapSendingMsg,
        WAutomationSequenceStep $wAutomationSequenceStep
    ): ?WAutomationLog {
        $filters = [
            'whatsapp_sending_message_id' => $wapSendingMsg->id,
            'wautomation_sequence_step_id' => $wAutomationSequenceStep->id,
        ];
        return WAutomationLog::where($filters)
            ->from(DB::raw('WAutomationsLogs USE INDEX(wautlogs_whatsapp_sending_message_id_index)'))
            ->limit(1)
            ->first()
        ;
    }


    public function findOneByLeadSaleAndWAutomationSequenceStep(
        LeadSale $triggeringLeadSale,
        WAutomationSequenceStep $wAutomationSequenceStep
    ): ?WAutomationLog {
        $filters = [
            'lead_sale_id' => $triggeringLeadSale->id,
            'lead_id' => $triggeringLeadSale->lead_id,
            'wautomation_sequence_step_id' => $wAutomationSequenceStep->id,
        ];
        return WAutomationLog::where($filters)
            ->from(DB::raw('WAutomationsLogs USE INDEX(wautlogs_lead_id_index)'))
            ->limit(1)
            ->first()
        ;
    }


    public function findOneByLeadIdAndWAutomationSequenceStep(
        int $leadId,
        WAutomationSequenceStep $wAutomationSequenceStep
    ): ?WAutomationLog {
        $filters = ['lead_id' => $leadId, 'wautomation_sequence_step_id' => $wAutomationSequenceStep->id];
        return WAutomationLog::where($filters)
            ->from(DB::raw('WAutomationsLogs USE INDEX(wautlogs_lead_id_index)'))
            ->limit(1)
            ->first()
        ;
    }


    public function findByLeadIdAndWAutomationSequenceStep(
        int $leadId,
        WAutomationSequenceStep $wAutomationSequenceStep
    ): Collection {
        $filters = ['lead_id' => $leadId, 'wautomation_sequence_step_id' => $wAutomationSequenceStep->id];
        return WAutomationLog::where($filters)
            ->from(DB::raw('WAutomationsLogs USE INDEX(wautlogs_lead_id_index)'))
            ->get()
        ;
    }


    public function findFailedWAPSenderJobsEnabledToRetry(
        User $user,
        DateTime $dateStart,
        array $errorsEnabledToRetry,
    ): Collection {
        $query = WAutomationLog::where('is_fully_applied', 0)
            ->where('client_id', $user->client_id)
            ->where('updated_at', '>=', $dateStart)
            // ->whereIn('exception', $errorsEnabledToRetry)
            ->whereHas('sentWhatsAppSendingMessage', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where(function ($q) use ($errorsEnabledToRetry) {
                foreach ($errorsEnabledToRetry as $error) {
                    $q->orWhereRaw('exception LIKE ?', ["%{$error}%"]);
                }
            })
        ;
        $logs = $query->get();
        return $logs;
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
