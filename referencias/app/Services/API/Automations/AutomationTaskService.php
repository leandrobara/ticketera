<?php

namespace App\Services\API\Automations;

use DateTime;
use Exception;
use Throwable;
use DateTimeZone;
use App\Models\User;
use App\Models\Lead;
use App\Models\Task;
use App\Models\Client;
use App\DTO\NewTaskDTO;
use App\Models\LeadSale;
use App\Models\TaskTemplate;
use App\Models\AutomationLog;
use App\Models\AutomationTask;
use App\Services\API\LeadService;
use App\Services\API\TaskService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Helpers\MermaidChartHelper;
use App\Services\API\LeadSaleService;
use App\Services\API\EventsLogService;
use App\Services\API\TaskTemplateService;
use App\DTO\Automations\AutomationTaskDTO;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\Automations\Parameters\ListAutomationTaskDTO;
use App\Repositories\Automations\AutomationTaskRepository;
use App\Services\API\Dispatchers\EmailEventsDispatcherService;
use App\Services\API\Actions\LeadService as ActionsLeadService;
use App\Services\API\Automations\Traits\AutomationTaskAfterSaleTrait;
use App\Exceptions\Services\Automations\AutomationNotToReportException;
use App\Services\API\Automations\Traits\AutomationTaskAfterTaskExpirationTrait;
use App\Services\API\Automations\Traits\AutomationTaskAfterTagStatusChangeTrait;


class AutomationTaskService
{

    use GetUserFromRequest;
    use GetClientFromRequest;
    use AutomationTaskAfterSaleTrait;
    use AutomationTaskAfterTaskExpirationTrait;
    use AutomationTaskAfterTagStatusChangeTrait;

    const LIMIT_WINDOW_HOURS = 3;


    public function __construct(
        protected readonly TaskService $taskService,
        protected readonly LeadService $leadService,
        protected readonly LeadSaleService $leadSaleService,
        protected readonly EventsLogService $eventsLogService,
        protected readonly MermaidChartHelper $mermaidChartHelper,
        protected readonly ActionsLeadService $actionsLeadService,
        protected readonly TaskTemplateService $taskTemplateService,
        protected readonly AutomationLogService $automationLogService,
        protected readonly AutomationTaskRepository $automationTaskRepository,
        protected readonly EmailEventsDispatcherService $emailEventsDispatcherService,
    ) {
    }


    public function list(ListAutomationTaskDTO $paramsDTO): Collection
    {
        $paramsDTO->client = $this->getClient(); //Override this just in case
        $automations = $this->automationTaskRepository->list($paramsDTO);
        return $automations;
    }


    public function create(AutomationTaskDTO $dto): AutomationTask
    {
        return $this->automationTaskRepository->create($dto);
    }


    public function update(AutomationTask $automationTask, AutomationTaskDTO $dto)
    {
        if (!$this->parametersChanged($automationTask, $dto)) {
            return $automationTask;
        }

        // If rule was never applied, I can update the row.
        $existentLog = $this->automationLogService->findOneByAutomationTask($automationTask, ['limit' => 1]);
        $ruleWasApplied = ($existentLog);
        if (!$ruleWasApplied) {
            $updatedAutomationTask = $this->automationTaskRepository->update($automationTask, $dto);
            return $updatedAutomationTask;
        }

        try {
            DB::beginTransaction();
            // If rule was applied at least once, I soft-delete it and create a new one.
            $this->automationTaskRepository->delete($automationTask);
            $newAutomation = $this->automationTaskRepository->create($dto);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $newAutomation;
    }


    public function parametersChanged(AutomationTask $automation, AutomationTaskDTO $dto)
    {
        if (
            $automation->create_hour == $dto->createHour &&
            $automation->trigger_type == $dto->triggerType &&
            $automation->is_recurrent == $dto->isRecurrent &&
            $automation->task_template_id == $dto->taskTemplateId &&
            $automation->create_delay_days == $dto->createDelayDays &&
            $automation->status_id_to_assign == $dto->statusToAssign?->id &&
            $automation->is_immediately_created == $dto->isImmediatelyCreated &&
            $automation->allowing_tags_ids == $dto->allowingTags->pluck('id')->toArray() &&
            $automation->tags_ids_to_assign == $dto->tagsToAssign->pluck('id')->toArray() &&
            $automation->allowing_status_ids == $dto->allowingStatus->pluck('id')->toArray() &&
            $automation->triggering_tags_ids == $dto->triggeringTags->pluck('id')->toArray() &&
            $automation->cancelling_tags_ids == $dto->cancellingTags->pluck('id')->toArray() &&
            $automation->cancelling_status_ids == $dto->cancellingStatus->pluck('id')->toArray() &&
            $automation->triggering_status_ids == $dto->triggeringStatus->pluck('id')->toArray()
        ) {
            return false;
        }

        return true;
    }


    public function findByClientAndTrigger(Client $client, string $triggerType): Collection
    {
        return $this->automationTaskRepository->findByClientAndTrigger($client, $triggerType);
    }


    public function findAutomationsByClient(Client $client): Collection
    {
        return $this->automationTaskRepository->findByClient($client);
    }


    public function findOthersByClientAndTriggerType(
        AutomationTask $automationTask,
        Client $client,
        string $triggerType
    ): Collection {
        return $this->automationTaskRepository->findOthersByClientAndTriggerType(
            $automationTask, $client, $triggerType
        );
    }


    public function delete(AutomationTask $automationTask): AutomationTask
    {
        $deletedAutomation = $this->automationTaskRepository->delete($automationTask);

        $this->emailEventsDispatcherService->dispatchSendDeletedAutomationEmailAlertJob(
            $deletedAutomation, $this->getUser()
        );
        return $deletedAutomation;
    }


    public function enable(AutomationTask $automationTask): AutomationTask
    {
        return $this->automationTaskRepository->enable($automationTask);
    }


    public function disable(AutomationTask $automationTask): AutomationTask
    {
        $disabledAutomation = $this->automationTaskRepository->disable($automationTask);
        $this->emailEventsDispatcherService->dispatchSendDisabledAutomationEmailAlertJob(
            $disabledAutomation, $this->getUser()
        );
        return $disabledAutomation;
    }


    // @param $leadTagStatusChangeEventLogs -> Collection<EventLog>
    protected function getExceptionIfNotEligible(
        AutomationTask $automationTask,
        Task | null $triggeringExpiredTask,
        LeadSale | null $triggeringLeadSale,
        Collection | null $leadTagStatusChangeEventLogs,
    ): Exception | AutomationNotToReportException | null {
        if (!$automationTask->enabled) {
            throw new Exception('automation_task_is_not_enabled');
        }
        if (!$automationTask->client) {
            throw new Exception('automation_task_client_was_deleted');
        }
        if (!$automationTask->client->enabled) {
            throw new Exception('automation_task_client_is_not_enabled');
        }

        // Todas menos after task expiration
        if (!$automationTask->isAfterTaskExpirationType) {
            if (!$automationTask->taskTemplate) {
                throw new Exception('automation_task_template_does_not_exist');
            }
            if (!$automationTask->create_delay_days) {
                throw new Exception('automation_task_create_delay_days_can_not_be_empty');
            }
            if (!$automationTask->create_hour) {
                throw new Exception('automation_task_create_hour_can_not_be_empty');
            }
        }

        // After task expiration
        if ($automationTask->isAfterTaskExpirationType) {
            $lead = $triggeringExpiredTask->lead;
            if (!$lead) {
                return new Exception('automation_triggering_expired_task_lead_does_not_exist');
            }
            if (!$triggeringExpiredTask->isExpired) {
                throw new Exception('automation_triggering_expired_task_is_not_expired');
            }
            if (!$triggeringExpiredTask->user) {
                throw new Exception('automation_triggering_expired_task_user_was_deleted');
            }
            if (!$triggeringExpiredTask->user->enabled) {
                throw new Exception('automation_triggering_expired_task_user_is_not_enabled');
            }
            if (!$automationTask->tags_ids_to_assign && !$automationTask->status_id_to_assign) {
                return new Exception('automation_task_tags_and_status_id_to_assign_can_not_be_both_empty');
            }
            if ($automationTask->tagsToAssign->isEmpty() && !$automationTask->statusToAssign) {
                return new Exception('automation_task_tags_and_status_to_assign_do_no_exist');
            }

            if ($automationTask->allowingTags->isNotEmpty()) {
                $allowingTagIds = $automationTask->allowingTags->pluck('id');
                $leadTagIds = $lead->tags->pluck('id');
                $matchingTags = $allowingTagIds->intersect($leadTagIds);
                if ($matchingTags->isEmpty()) {
                    $msg = "task_lead_does_not_containallowing_tags: lead.tagIds: {$leadTagIds->toJson()} | ";
                    $msg .= "automationTask.allowingTagIds: {$allowingTagIds->toJson()}";
                    return new AutomationNotToReportException($msg);
                }
            }
            if ($automationTask->allowingStatus->isNotEmpty()) {
                $allowingStatusIds = $automationTask->allowingStatus->pluck('id');
                $leadHasMatchingStatus = $allowingStatusIds->contains($lead->status_id);
                if (!$leadHasMatchingStatus) {
                    $msg = "task_lead_does_not_containallowing_status: lead.statusId: {$lead->status_id} |";
                    $msg .= "automationTask.allowingStatusIds: {$allowingStatusIds->toJson()}";
                    return new AutomationNotToReportException($msg);
                }
            }
        }

        // After sale
        if ($automationTask->isAfterSaleType) {
            if (!$triggeringLeadSale) {
                throw new Exception('automation_task_lead_sale_does_not_exist');
            }
            if (!$triggeringLeadSale->user) {
                throw new Exception('automation_task_lead_sale_user_was_deleted');
            }
            if (!$triggeringLeadSale->user->enabled) {
                throw new Exception('automation_task_lead_sale_user_is_not_enabled');
            }
            $lead = $triggeringLeadSale->lead;
            if (!$lead) {
                return new Exception('automation_task_lead_sale_lead_does_not_exist');
            }
        }

        // After tag/status change
        if ($automationTask->isAfterTagChangeType || $automationTask->isAfterStatusChangeType) {
            if (!$leadTagStatusChangeEventLogs || $leadTagStatusChangeEventLogs->isEmpty()) {
                return new Exception('automation_task_lead_tag_status_change_event_logs_can_not_be_empty');
            }
            if ($automationTask->isAfterTagChangeType && $automationTask->isAfterStatusChangeType) {
                return new Exception('automation_task_triggering_tags_and_status_can_not_use_at_the_same_time');
            }

            $leadId = $leadTagStatusChangeEventLogs->first()->log['lead']['id'];
            $lead = $this->leadService->find($leadId, ['failIfNotExists' => false]);
            if (!$lead) {
                return new Exception('automation_task_lead_tag_status_change_events_lead_no_longer_exists');
            }

            if ($automationTask->isAfterTagChangeType) {
                if (!$automationTask->triggering_tags_ids) {
                    throw new Exception('automation_task_triggering_tags_are_empty');
                }
                $leadHasTriggeringTag = $automationTask->triggeringTags->intersect($lead->tags)->isNotEmpty();
                if (!$leadHasTriggeringTag) {
                    $msg = 'automation_task_lead_has_not_triggering_tag_anymore';
                    return new AutomationNotToReportException($msg);
                }
            }

            if ($automationTask->isAfterStatusChangeType) {
                if (!$automationTask->triggering_status_ids) {
                    throw new Exception('automation_task_triggering_status_are_empty');
                }
                $triggeringStatusList = $automationTask->triggeringStatus;
                $leadHasTriggeringStatus = $triggeringStatusList->contains($lead->status);
                if (!$leadHasTriggeringStatus) {
                    $msg = 'automation_task_lead_has_not_triggering_status_anymore';
                    return new AutomationNotToReportException($msg);
                }
            }
        }

        if ($automationTask->cancellingStatus->isNotEmpty()) {
            $leadHasCancellingStatus = $automationTask->cancellingStatus->contains($lead->status);
            if ($leadHasCancellingStatus) {
                $msg = 'automation_task_lead_has_cancelling_status';
                return new AutomationNotToReportException($msg);
            }
        }

        if ($automationTask->cancellingTags->isNotEmpty()) {
            $leadHasCancellingTag = $automationTask->cancellingTags->intersect($lead->tags)->isNotEmpty();
            if ($leadHasCancellingTag) {
                $msg = 'automation_task_lead_has_cancelling_tag';
                return new AutomationNotToReportException($msg);
            }
        }

        return null;
    }


    protected function buildNewTaskDTO(
        Lead $lead,
        TaskTemplate $taskTemplate,
        AutomationLog $automationLog,
    ): NewTaskDTO {
        $data = [];
        $data['lead_id'] = $lead->id;
        $data['user_id'] = $lead->user->id;
        $data['client_id'] = $lead->client->id;
        $data['automation_log_id'] = $automationLog->id;
        $data['description'] = $taskTemplate->description;
        $data['is_important'] = $taskTemplate->is_important;
        $data['title'] = $taskTemplate->title ?? '<<Sin título>>';
        $data['limit_date'] = $this->getNewTaskLimitDate($taskTemplate, $lead->client);
        return NewTaskDTO::build($data);
    }


    protected function getNewTaskLimitDate(TaskTemplate $taskTemplate, Client $client): DateTime
    {
        $limitDateDays = $taskTemplate->limit_date_days ?? 0;
        $limitDateHour = $taskTemplate->limit_date_hour ?? '23:59';

        [$hour, $minutes] = explode(':', $limitDateHour);

        $dateNow = $this->getDateNow();
        $clientTz = new DateTimeZone($client->timezone);
        $clientDateNow = (clone $dateNow)->setTimezone($clientTz);

        $clientLimitDate = $clientDateNow->modify("+ {$limitDateDays} days")
            ->setTime($hour, $minutes, 0)
        ;
        $systemLimitDate = $clientLimitDate->setTimezone(new DateTimeZone('UTC'));
        return $systemLimitDate;
    }


    private function getStartDateToSearchEvents(AutomationTask $automationTask): DateTime
    {
        $client = $automationTask->client;
        $dateTime = $this->getDateNow();
        $systemTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($client->timezone);
        $dateTime->setTimezone($clientTz);

        // Que hora es para el sistema cuando para el cliente son las 00:00
        $dateTime->modify("- {$automationTask->create_delay_days} days")->setTime(0, 0, 0)->setTimezone($systemTz);
        return $dateTime;
    }


    private function getEndDateToSearchEvents(AutomationTask $automationTask): DateTime
    {
        $client = $automationTask->client;
        $dateTime = $this->getDateNow();
        $systemTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($client->timezone);
        $dateTime->setTimezone($clientTz);

        // Que hora es para el sistema cuando para el cliente son las 23:59:59
        $dateTime->modify("- {$automationTask->create_delay_days} days")->setTime(23, 59, 59)->setTimezone($systemTz);
        return $dateTime;
    }


    public function isInHourToApply(AutomationTask $automationTask): bool
    {
        $dateNow = $this->getDateNow();
        $hourNow = (int) $dateNow->format('H');
        $hourArr = explode(':', $automationTask->create_hour);
        $hourToApply = (int) $hourArr[0];
        $hoursAbsoluteDiff = absoluteHoursDiff($hourNow, $hourToApply);
        if ($hoursAbsoluteDiff <= self::LIMIT_WINDOW_HOURS) {
            return true;
        }
        return false;
    }


    private function isTimeToApplyAutomationTask(AutomationTask $automationTask, DateTime $triggerDate): bool
    {
        $dateNow = $this->getDateNow();
        $taskCreationDate = $this->getTaskCreationDate($automationTask, $triggerDate);

        $limitWindowHours = self::LIMIT_WINDOW_HOURS;
        $maxTaskCreationDate = (clone($taskCreationDate))->modify("+{$limitWindowHours} hours");

        $sendWindowExceeded = $dateNow >= $maxTaskCreationDate;
        $taskCreationDateReached = $dateNow >= $taskCreationDate;
        return ($taskCreationDateReached && !$sendWindowExceeded);
    }


    /**
     * $triggerDate -> puede ser fecha de venta, o fecha de cambio de estado/etiqueta
     **/
    private function getTaskCreationDate(AutomationTask $automationTask, DateTime $triggerDate): DateTime
    {
        $systemTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($automationTask->client->timezone);

        $taskCreationDate = (clone $triggerDate);
        // Uso el TZ del cliente para saber que día era para el cliente, y luego sumarle XX días.
        $taskCreationDate->setTimezone($clientTz)->setTime(0, 0, 0);
        $taskCreationDate->modify('+ ' . $automationTask->create_delay_days . ' days');

        // Uso el TZ del cliente para saber la hora en su timezone.
        $hourArr = explode(':', $automationTask->create_hour);
        $clientTaskCreationDate = ($this->getDateNow())->setTime((int) $hourArr[0], (int) $hourArr[1], 0);
        $clientTaskCreationDate = $clientTaskCreationDate->setTimezone($clientTz);
        
        $clientHourArr = explode(':', $clientTaskCreationDate->format('H:i'));
        $taskCreationDate->setTime((int) $clientHourArr[0], (int) $clientHourArr[1], 0);

        // Una vez que tengo armada la fecha/hora en TZ del cliente, la paso a UTC0.
        $taskCreationDate->setTimezone($systemTz);
        return $taskCreationDate;
    }


    private function getDateNow(): DateTime
    {
        // return environmentIsNotProduction() ? new DateTime('2023-10-16 13:10:00') : new DateTime('now');
        return new DateTime('now');
    }


    public function getFlowChartMarkdownString(AutomationTask $automationTask): string
    {
        $markdown = $this->mermaidChartHelper->buildAutomationTaskMarkdown($automationTask);
        return $markdown;
    }

}
