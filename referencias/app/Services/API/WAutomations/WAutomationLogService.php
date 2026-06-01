<?php

namespace App\Services\API\WAutomations;

use DateTime;
use Throwable;
use Exception;
use App\Models\Lead;
use App\Models\User;
use App\Models\LeadSale;
use Illuminate\Http\Request;
use App\Models\WAutomationLog;
use App\Models\WhatsAppSending;
use Illuminate\Support\Collection;
use App\Models\WAutomationSequence;
use App\Models\WAutomationAfterSend;
use App\Models\WhatsAppSendingMessage;
use App\Models\WAutomationSequenceStep;
use App\Models\WAutomationProposalResendRule;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Sort\SortCriteria;
use App\Models\WAutomationProposalModifyLeadAfterSendRule;
use App\Repositories\WAutomations\WAutomationLogRepository;
use App\Repositories\Criteria\Sort\WAutomation\SortByCreated;
use App\Repositories\Criteria\Filter\WAutomation\TypeLogCriteria;
use App\Repositories\Criteria\Filter\WAutomation\DateEndCriteria;
use App\Repositories\Criteria\Filter\WAutomation\DateStartCriteria;


class WAutomationLogService
{

    use GetClientFromRequest;


    public function __construct(WAutomationLogRepository $wAutomationLogRepository)
    {
        $this->wAutomationLogRepository = $wAutomationLogRepository;
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
        return $this->wAutomationLogRepository->listPaginated($client, $repoOpts);
    }


    public function markAsNotApplied(WAutomationLog $wAutLog, ?string $exceptionMsg = null): WAutomationLog
    {
        return $this->wAutomationLogRepository->update(
            $wAutLog, ['is_fully_applied' => false, 'exception' => $exceptionMsg]
        );
    }


    public function markAsApplied(WAutomationLog $wAutLog): WAutomationLog
    {
        return $this->wAutomationLogRepository->update($wAutLog, ['is_fully_applied' => true, 'exception' => null]);
    }


    public function createWAutomationAfterSendLog(
        WhatsAppSendingMessage $wapSendingMsg,
        WAutomationAfterSend $wAutomationAfterSend,
        ?Exception $exception = null
    ): WAutomationLog {
        $applied = $exception ? false : true;
        $data = [
            'is_fully_applied' => $applied,
            'lead_id' => $wapSendingMsg->lead_id,
            'client_id' => $wapSendingMsg->client_id,
            'exception' => $exception?->getMessage(),
            'whatsapp_sending_message_id' => $wapSendingMsg->id,
            'wautomation_after_send_id' => $wAutomationAfterSend->id,
            'whatsapp_sending_id' => $wapSendingMsg->whatsapp_sending_id,
        ];
        return $this->wAutomationLogRepository->create($data);
    }


    public function createWAutomationProposalResendLog(
        WhatsAppSendingMessage $wapSendingMsg,
        WAutomationProposalResendRule $rule,
        Exception | null $exception = null
    ): WAutomationLog {
        $applied = $exception ? false : true;
        $data = [
            'is_fully_applied' => $applied,
            'lead_id' => $wapSendingMsg->lead_id,
            'client_id' => $wapSendingMsg->client_id,
            'exception' => $exception?->getMessage(),
            'wautomation_proposal_resend_rule_id' => $rule->id,
            'whatsapp_sending_message_id' => $wapSendingMsg->id,
            'wautomation_proposal_id' => $rule->wautomation_proposal_id,
            'whatsapp_sending_id' => $wapSendingMsg->whatsapp_sending_id,
        ];
        return $this->wAutomationLogRepository->create($data);
    }


    public function createAfterSentProposalWAutomationSequenceStepLog(
        WhatsAppSendingMessage $triggeringProposalWapSendingMsg,
        WAutomationSequenceStep $wAutSequenceStep,
        Exception | null $exception = null
    ): WAutomationLog {
        $applied = $exception ? false : true;
        $data = [
            'is_fully_applied' => $applied,
            'exception' => $exception?->getMessage(),
            'lead_id' => $triggeringProposalWapSendingMsg->lead_id,
            'wautomation_sequence_step_id' => $wAutSequenceStep->id,
            'client_id' => $triggeringProposalWapSendingMsg->client_id,
            'whatsapp_sending_message_id' => $triggeringProposalWapSendingMsg->id,
            'wautomation_sequence_id' => $wAutSequenceStep->wautomation_sequence_id,
            'whatsapp_sending_id' => $triggeringProposalWapSendingMsg->whatsapp_sending_id,
        ];
        return $this->wAutomationLogRepository->create($data);
    }


    public function createAfterSaleWAutomationSequenceStepLog(
        LeadSale $triggeringLeadSale,
        WAutomationSequenceStep $wAutSequenceStep,
        Exception | null $exception = null
    ): WAutomationLog {
        $applied = $exception ? false : true;
        $data = [
            'is_fully_applied' => $applied,
            'exception' => $exception?->getMessage(),
            'lead_sale_id' => $triggeringLeadSale->id,
            'lead_id' => $triggeringLeadSale->lead_id,
            'client_id' => $triggeringLeadSale->client_id,
            'wautomation_sequence_step_id' => $wAutSequenceStep->id,
            'wautomation_sequence_id' => $wAutSequenceStep->wautomation_sequence_id,
        ];
        return $this->wAutomationLogRepository->create($data);
    }


    public function createWAutomationProposalModifyLeadAfterSendLog(
        WhatsAppSendingMessage $wapSendingMsg,
        WAutomationProposalModifyLeadAfterSendRule $rule
    ): WAutomationLog {
        $data = [
            'is_fully_applied' => true,
            'lead_id' => $wapSendingMsg->lead_id,
            'client_id' => $wapSendingMsg->client_id,
            'whatsapp_sending_message_id' => $wapSendingMsg->id,
            'wautomation_proposal_id' => $rule->wautomation_proposal_id,
            'whatsapp_sending_id' => $wapSendingMsg->whatsapp_sending_id,
            'wautomation_proposal_modify_lead_after_send_rule_id' => $rule->id
        ];
        return $this->wAutomationLogRepository->create($data);
    }


    // @param $leadTagStatusChangeEventLogs -> Collection<EventLog>
    public function createAfterTagStatusChangeWAutomationSequenceStepLog(
        Collection $leadTagStatusChangeEventLogs,
        WAutomationSequenceStep $wAutSequenceStep,
        Exception | null $exception = null
    ): WAutomationLog {
        $applied = $exception ? false : true;
        $data = [
            'is_fully_applied' => $applied,
            'exception' => $exception?->getMessage(),
            'wautomation_sequence_step_id' => $wAutSequenceStep->id,
            'event_log_ids' => $leadTagStatusChangeEventLogs->pluck('id'),
            'lead_id' => $leadTagStatusChangeEventLogs->first()->log['lead']['id'],
            'client_id' => $leadTagStatusChangeEventLogs->first()->log['client_id'],
            'wautomation_sequence_id' => $wAutSequenceStep->wautomation_sequence_id,
        ];
        return $this->wAutomationLogRepository->create($data);
    }

    
    public function findOneByWAutomationAfterSend(WAutomationAfterSend $wAutomationAfterSend): ?WAutomationLog
    {
        return $this->wAutomationLogRepository->findOneByWAutomationAfterSend($wAutomationAfterSend);
    }
    

    public function findOneByWAutomationSequence(WAutomationSequence $wAutomationSequence): ?WAutomationLog
    {
        return $this->wAutomationLogRepository->findOneByWAutomationSequence($wAutomationSequence);
    }


    public function findOneByWAutomationSequenceStep(wAutomationSequenceStep $wAutomationSequenceStep): ?WAutomationLog
    {
        return $this->wAutomationLogRepository->findOneByWAutomationSequenceStep($wAutomationSequenceStep);
    }


    public function findOneAfterSendByWhatsAppSendingMessage(
        WhatsAppSendingMessage $wapSendingMsg
    ): ?WAutomationLog {
        return $this->wAutomationLogRepository->findOneAfterSendByWhatsAppSendingMessage($wapSendingMsg);
    }


    public function findOneAfterSendByLeadId(int $leadId): ?WAutomationLog
    {
        return $this->wAutomationLogRepository->findOneAfterSendByLeadId($leadId);
    }


    public function findOneByWAutomationProposalResendRule(
        WAutomationProposalResendRule $rule
    ): ?WAutomationLog {
        return $this->wAutomationLogRepository->findOneByWAutomationProposalResendRule($rule);
    }


    public function findOneByWAutomationProposalModifyLeadAfterSendRule(
        WAutomationProposalModifyLeadAfterSendRule $rule
    ): ?WAutomationLog {
        return $this
            ->wAutomationLogRepository
            ->findOneByWAutomationProposalModifyLeadAfterSendRule($rule)
        ;
    }

    public function findOneByLeadAndWAutomationProposalModifyLeadAfterSendRule(
        Lead $lead,
        WAutomationProposalModifyLeadAfterSendRule $rule
    ): ?WAutomationLog {
        return $this
            ->wAutomationLogRepository
            ->findOneByLeadAndWAutomationProposalModifyLeadAfterSendRule($lead, $rule)
        ;
    }


    public function findOneByWhatsAppSendingMessageAndWAutomationProposalResendRule(
        WhatsAppSendingMessage $whatsAppSendingMessage,
        WAutomationProposalResendRule $rule
    ): ?WAutomationLog {
        return $this
            ->wAutomationLogRepository
            ->findOneByWhatsAppSendingMessageAndWAutomationProposalResendRule($whatsAppSendingMessage, $rule)
        ;
    }


    public function findOneByWhatsAppSendingMessageAndWAutomationSequenceStep(
        WhatsAppSendingMessage $wapSendingMsg,
        WAutomationSequenceStep $wAutomationSequenceStep
    ): ?WAutomationLog {
        return $this
            ->wAutomationLogRepository
            ->findOneByWhatsAppSendingMessageAndWAutomationSequenceStep($wapSendingMsg, $wAutomationSequenceStep)
        ;
    }


    public function findOneByLeadSaleAndWAutomationSequenceStep(
        LeadSale $triggeringLeadSale,
        WAutomationSequenceStep $wAutomationSequenceStep
    ): ?WAutomationLog {
        return $this
            ->wAutomationLogRepository
            ->findOneByLeadSaleAndWAutomationSequenceStep($triggeringLeadSale, $wAutomationSequenceStep)
        ;
    }


    public function findOneByLeadIdAndWAutomationSequenceStep(
        int $leadId,
        WAutomationSequenceStep $wAutomationSequenceStep
    ): ?WAutomationLog {
        return $this
            ->wAutomationLogRepository
            ->findOneByLeadIdAndWAutomationSequenceStep($leadId, $wAutomationSequenceStep)
        ;
    }


    public function findByLeadIdAndWAutomationSequenceStep(
        int $leadId,
        WAutomationSequenceStep $wAutomationSequenceStep
    ): Collection {
        return $this
            ->wAutomationLogRepository
            ->findByLeadIdAndWAutomationSequenceStep($leadId, $wAutomationSequenceStep)
        ;
    }


    public function findFailedWAPSenderJobsEnabledToRetry(User $user, array $errorsEnabledToRetry): Collection
    {
        if (!$user->wap_sender_retry_delay_days) {
            return new Collection();
        }
        $retryMaxDays = $user->wap_sender_retry_delay_days;
        $dateStart = $this->calculateStartDateSkippingWeekends($retryMaxDays);
        return $this->wAutomationLogRepository->findFailedWAPSenderJobsEnabledToRetry(
            $user, $dateStart, $errorsEnabledToRetry
        );
    }


    private function getFilterCriteriasByName($filters)
    {
        $nfilters = [];
        $criterias = [
            'type' => TypeLogCriteria::class,
            'date_end' => DateEndCriteria::class,
            'date_start' => DateStartCriteria::class,
        ];
        foreach ($filters as $key => $value) {
            if (in_array($key, array_keys($criterias))) {
                $nfilters[$key] = new $criterias[$key]($value);
            } else {
                $nfilters[$key] =  $value;
            }
        }
        return $nfilters;
    }


    private function getSortCriteriasByName(string $sortsName): SortCriteria | string
    {
        $sortTypes = ['date_asc' => new SortByCreated('asc'), 'date_desc' => new SortByCreated('desc')];
        return $sortTypes[$sortsName] ?? '';
    }


    private function calculateStartDateSkippingWeekends(int $businessDays): DateTime
    {
        $daysToSubtract = 0;
        $businessDaysCount = 0;
        $currentDate = new DateTime();
        while ($businessDaysCount < $businessDays) {
            $daysToSubtract++;
            $checkDate = clone $currentDate;
            $checkDate->modify("-{$daysToSubtract} days");
            if (!$this->dateIsWeekend($checkDate)) {
                $businessDaysCount++;
            }
        }
        $dateStart = (clone $currentDate)->modify("-{$daysToSubtract} days");
        return $dateStart;
    }


    private function dateIsWeekend(DateTime $date): bool
    {
        return ((int) $date->format('w')) == 0 || ((int) $date->format('w')) == 6;
    }

}
