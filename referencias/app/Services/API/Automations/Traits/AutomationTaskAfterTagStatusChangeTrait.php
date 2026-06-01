<?php

namespace App\Services\API\Automations\Traits;

use DateTime;
use Exception;
use Throwable;
use DateTimeZone;
use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use App\Models\LeadSale;
use App\Models\AutomationLog;
use App\Helpers\PhonesHelper;
use App\Models\AutomationTask;
use App\Models\MongoDB\EventLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\API\StatusService;
use App\Services\API\LeadSaleService;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\Notifications\NotificationService;
use App\Exceptions\Services\Automations\AutomationNotToReportException;


trait AutomationTaskAfterTagStatusChangeTrait
{

    // @param $leadEventLogs -> Collection<EventLog>
    public function applyAfterTagStatusChange(Collection $leadEventLogs, AutomationTask $automationTask): Collection
    {
        $automationTaskWasAlreadyApplied = $this->automationTaskAfterTagStatusChangeHasBeenApplied(
            $automationTask, $leadEventLogs
        );
        if ($automationTaskWasAlreadyApplied) {
            return new Collection();
        }

        $exception = $this->getExceptionIfNotEligible(
            triggeringLeadSale: null,
            triggeringExpiredTask: null,
            automationTask: $automationTask,
            leadTagStatusChangeEventLogs: $leadEventLogs,
        );
        if ($exception) {
            $automationLog = $this->automationLogService->createAutomationTaskAfterTagStatusChangeLog(
                $leadEventLogs, $automationTask, $exception
            );
            if (!($exception instanceof AutomationNotToReportException)) {
                report($exception);
            }
            return new Collection([$automationLog]);
        }

        try {
            DB::beginTransaction();

            $lead = $this->leadService->find((int) $leadEventLogs->first()->log['lead']['id']);
            $automationLog = $this->automationLogService->createAutomationTaskAfterTagStatusChangeLog(
                $leadEventLogs, $automationTask
            );
            $newTaskDTO = $this->buildNewTaskDTO(
                lead: $lead,
                automationLog: $automationLog,
                taskTemplate: $automationTask->taskTemplate,
            );
            $this->taskService->create($newTaskDTO->toArray());

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return new Collection([$automationLog]);
    }


    public function findEnabledAfterTagStatusChangeAutomationByClient(Client $client): Collection
    {
        return $this->automationTaskRepository->findEnabledAfterTagStatusChangeAutomationByClient($client);
    }


    // @return Collection<leadId => Collection<EventLog>>
    public function findEventLogsEnabledToSendGroupedByLeadId(AutomationTask $automationTask): Collection
    {
        $enabledTriggeringEventLogs = new Collection();
        $dateEnd = $this->getEndDateToSearchEvents($automationTask);
        $dateStart = $this->getStartDateToSearchEvents($automationTask);
        $triggeringEventLogs = $this->eventsLogService->findStatusOrTagChangeEventLogsByAutomation(
            $automationTask, $dateStart, $dateEnd
        );
        foreach ($triggeringEventLogs as $eventLog) {
            $isTimeToSend = $this->isTimeToApplyAutomationTask($automationTask, $eventLog->createdAt->toDateTime());
            if ($isTimeToSend) {
                $enabledTriggeringEventLogs->push($eventLog);
            }
        }
        // Filtro de protección extra.
        $enabledTriggeringEventLogs = $enabledTriggeringEventLogs->filter(
            function (EventLog $eventLog) use ($dateStart, $dateEnd) {
                $eventLogDate = $eventLog->createdAt->toDateTime();
                return $eventLogDate >= $dateStart && $eventLogDate <= $dateEnd;
            }
        );

        if ($automationTask->is_recurrent) {
            $automationLogs = $this->automationLogService->findAppliedByAutomationTaskBetweenDates(
                $automationTask, $dateStart, $dateEnd
            );
            $enabledEventLogIds = new Collection();
            foreach ($automationLogs as $automationLog) {
                $isTimeToSend = $this->isTimeToApplyAutomationTask($automationTask, $automationLog->created_at);
                if ($isTimeToSend) {
                    $enabledEventLogIds->push($automationLog->event_log_ids);
                }
            }
            $enabledEventLogIds = $enabledEventLogIds->flatten()->unique()->values()->toArray();
            if ($enabledEventLogIds) {
                $opts = [
                    'filters' => [
                        '_id' => $enabledEventLogIds,
                        'event' => $automationTask->isTagTriggered ? 'lead_tag_added' : 'lead_status_updated',
                    ],
                    'limit' => count($enabledEventLogIds),
                ];
                $triggeringEventLogs = $this->eventsLogService->list($automationTask->client, $opts);
                // Filtro de protección extra.
                $triggeringEventLogs = $triggeringEventLogs
                    ->filter(function (EventLog $eventLog) use ($enabledEventLogIds) {
                        return in_array($eventLog->id, $enabledEventLogIds);
                    })
                ;
                $enabledTriggeringEventLogs = $enabledTriggeringEventLogs->merge($triggeringEventLogs);
            }
        }

        // Filtro de protección extra.
        $enabledTriggeringEventLogs = $enabledTriggeringEventLogs->filter(
            function (EventLog $eventLog) use ($automationTask): bool {
                $eventName = $automationTask->isTagTriggered ? 'lead_tag_added' : 'lead_status_updated';
                $isCorrectEvent = $eventLog->event == $eventName;
                $isCorrectClient = $eventLog->log['client_id'] == $automationTask->client_id;
                return $isCorrectClient && $isCorrectEvent;
            }
        );

        $eventLogsGroupedByLead = $enabledTriggeringEventLogs->groupBy('log.lead.id');
        return $eventLogsGroupedByLead;
    }


    // @param $leadEventLogs -> array<EventLog>
    public function automationTaskAfterTagStatusChangeHasBeenApplied(
        AutomationTask $automationTask,
        Collection $leadEventLogs,
    ): bool {
        $leadId = (int) $leadEventLogs->first()->log['lead']['id'];
        $lastAutomationLog = $this->automationLogService->findLastOneByAutomationTaskAndLeadId(
            $automationTask, $leadId
        );
        if (!$lastAutomationLog) {
            return false;
        }
        
        if ($automationTask->is_recurrent) {
            $dateNow = $this->getDateNow();
            $wasAppliedToday = $lastAutomationLog->created_at->format('Y-m-d') == $dateNow->format('Y-m-d');
            return $wasAppliedToday ? true : false;
        }
        return true;
    }

}
