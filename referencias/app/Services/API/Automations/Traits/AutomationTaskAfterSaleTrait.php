<?php

namespace App\Services\API\Automations\Traits;

use DateTime;
use Exception;
use Throwable;
use DateTimeZone;
use App\Models\User;
use App\Models\Lead;
use App\Models\Task;
use App\Models\Client;
use App\Models\LeadSale;
use App\Models\TaskTemplate;
use App\Models\AutomationLog;
use App\Models\AutomationTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\Services\Automations\AutomationNotToReportException;


trait AutomationTaskAfterSaleTrait
{

    public function findEnabledAutomationTaskAfterSaleByClient(Client $client): Collection
    {
        return $this->automationTaskRepository->findEnabledAutomationTaskAfterSaleByClient($client);
    }


    public function applyAfterSale(
        AutomationTask $automationTask,
        LeadSale $triggeringLeadSale,
    ): Collection {
        $hasBeenApplied = $this->automationTaskAfterSaleHasBeenApplied($automationTask, $triggeringLeadSale);
        if ($hasBeenApplied) {
            return new Collection();
        }

        $exception = $this->getExceptionIfNotEligible(
            triggeringExpiredTask: null,
            automationTask: $automationTask,
            leadTagStatusChangeEventLogs: null,
            triggeringLeadSale: $triggeringLeadSale,
        );

        if ($exception) {
            $automationLog = $this->automationLogService->createAutomationTaskAfterSaleLog(
                $triggeringLeadSale, $automationTask, $exception
            );
            if (!($exception instanceof AutomationNotToReportException)) {
                report($exception);
            }
            return new Collection([$automationLog]);
        }

        try {
            DB::beginTransaction();

            $automationLog = $this->automationLogService->createAutomationTaskAfterSaleLog(
                $triggeringLeadSale, $automationTask
            );
            $newTaskDTO = $this->buildNewTaskDTO(
                automationLog: $automationLog,
                lead: $triggeringLeadSale->lead,
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


    public function findLeadSalesEnabledToCreateTasks(AutomationTask $automationTask): Collection
    {
        $enabledLeadSales = new Collection();
        $dateEnd = $this->getEndDateToSearchEvents($automationTask);
        $dateStart = $this->getStartDateToSearchEvents($automationTask);

        if ($automationTask->is_recurrent) {
            $automationLogs = $this->automationLogService->findAppliedByAutomationTaskBetweenDates(
                $automationTask, $dateStart, $dateEnd
            );
            foreach ($automationLogs as $automationLog) {
                $isTimeToSend = $this->isTimeToApplyAutomationTask($automationTask, $automationLog->created_at);
                if ($isTimeToSend) {
                    $enabledLeadSales->push($automationLog->leadSale);
                }
            }
        }

        $leadSales = $this->leadSaleService->findByClientAndDates($automationTask->client, $dateStart, $dateEnd);
        foreach ($leadSales as $leadSale) {
            $isTimeToSend = $this->isTimeToApplyAutomationTask($automationTask, $leadSale->sale_date);
            if ($isTimeToSend) {
                $enabledLeadSales->push($leadSale);
            }
        }
        return $enabledLeadSales->unique()->values();
    }


    public function automationTaskAfterSaleHasBeenApplied(
        AutomationTask $automationTask,
        LeadSale $triggeringLeadSale
    ): bool {
        $lastAutomationLog = $this->automationLogService->findLastOneByAutomationTaskAndLeadSale(
            $automationTask, $triggeringLeadSale
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
