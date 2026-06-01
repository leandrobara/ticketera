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
use App\Models\AutomationLog;
use App\Models\AutomationTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\Services\Automations\AutomationNotToReportException;


trait AutomationTaskAfterTaskExpirationTrait
{

    public function findEnabledAutomationTaskAfterTaskExpirationByClient(Client $client): Collection
    {
        return $this->automationTaskRepository->findEnabledAutomationTaskAfterTaskExpirationByClient($client);
    }


    public function applyAfterTaskExpiration(
        AutomationTask $automationTask,
        Task $triggeringTask,
    ): Collection {
        $hasBeenApplied = $this->automationTaskAfterTaskExpirationHasBeenApplied($automationTask, $triggeringTask);
        if ($hasBeenApplied) {
            return new Collection();
        }

        $exception = $this->getExceptionIfNotEligible(
            triggeringLeadSale: null,
            automationTask: $automationTask,
            leadTagStatusChangeEventLogs: null,
            triggeringExpiredTask: $triggeringTask,
        );
        if ($exception) {
            $automationLog = $this->automationLogService->createAutomationTaskAfterTaskExpirationLog(
                $triggeringTask, $automationTask, $exception
            );
            if (!($exception instanceof AutomationNotToReportException)) {
                report($exception);
            }
            return new Collection([$automationLog]);
        }

        try {
            DB::beginTransaction();

            $automationLog = $this->automationLogService->createAutomationTaskAfterTaskExpirationLog(
                $triggeringTask, $automationTask
            );
            
            if ($automationTask->tagsToAssign->isNotEmpty()) {
                $lead = $this->addLeadTags($triggeringTask->lead, $automationTask);
            }
            if ($automationTask->statusToAssign) {
                $this->actionsLeadService->changeStatus($triggeringTask->lead, $automationTask->statusToAssign);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return new Collection([$automationLog]);
    }


    public function automationTaskAfterTaskExpirationHasBeenApplied(
        AutomationTask $automationTask,
        Task $triggeringTask
    ): bool {
        $lastAutomationLog = $this->automationLogService->findLastOneByAutomationTaskAndTriggeringTask(
            $automationTask, $triggeringTask
        );
        if (!$lastAutomationLog) {
            return false;
        }
        return true;
    }


    protected function addLeadTags(Lead $lead, AutomationTask $automationTask): Lead
    {
        $leadTags = $lead->tags;
        $tagsToAssign = $automationTask->tagsToAssign;
        $tagsToAssign = $tagsToAssign->filter(function ($tagToAdd) use ($leadTags) {
            $alreadyAsssignedTag = $leadTags->where('id', $tagToAdd->id)->first();
            return $alreadyAsssignedTag ? false : true;
        });
        $allTags = $leadTags->merge($tagsToAssign);
        if ($allTags) {
            $this->actionsLeadService->setLeadTags($lead, $allTags);
        }
        return $lead;
    }

}
