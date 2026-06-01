<?php

namespace App\Http\Controllers\API\Worker;

use DateTime;
use Throwable;
use Exception;
use DateTimeZone;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Models\AutomationTask;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Automations\AutomationTaskService;
use App\Services\API\Views\TaskService as ViewsTaskService;
use App\Helpers\WorkerOutputFormatter;


class AutomationTaskWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(500);
    }


    public function applyAutomationsTaskAfterSale(Request $request)
    {
        $lockKey = 'applyAutomationsTaskAfterSale';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 600)) {
            die('Locked');
        }

        $service = resolve(AutomationTaskService::class);
        $clients = resolve(ClientService::class)->findAllEnabled();

        foreach ($clients as $client) {
            SystemHelper::doFlush();
            
            try {
                $automationsTasks = $service->findEnabledAutomationTaskAfterSaleByClient($client);
                if ($automationsTasks->isEmpty()) {
                    continue;
                }

                $this->printClientInfo($client);
                foreach ($automationsTasks as $automationTask) {
                    if (!$service->isInHourToApply($automationTask)) {
                        continue;
                    }
                    $appliedAutomationLogs = new Collection();
                    $this->printAutomationTaskInfo($automationTask);

                    $triggeringLeadSales = $service->findLeadSalesEnabledToCreateTasks($automationTask);
                    resolve(LockHelper::class)->getLockByName($lockKey, 600);

                    foreach ($triggeringLeadSales as $triggeringLeadSale) {
                        $logs = $service->applyAfterSale($automationTask, $triggeringLeadSale);
                        $appliedAutomationLogs = $appliedAutomationLogs->merge($logs);
                    }
                    $this->printAutomationLogsInfo($appliedAutomationLogs);
                }
            } catch (Exception $e) {
                dump($e);
                report($e);
            }
            $this->printSeparator();
            resolve(LockHelper::class)->getLockByName($lockKey, 90);
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function applyAutomationsTaskAfterTaskExpiration(Request $request)
    {
        $lockKey = 'applyAutomationsTaskAfterTaskExpiration';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 600)) {
            die('Locked');
        }

        $taskService = resolve(ViewsTaskService::class);
        $clients = resolve(ClientService::class)->findAllEnabled();
        $automationTaskService = resolve(AutomationTaskService::class);

        foreach ($clients as $client) {
            SystemHelper::doFlush();
            
            try {
                $automationsTasks = $automationTaskService->findEnabledAutomationTaskAfterTaskExpirationByClient(
                    $client
                );
                if ($automationsTasks->isEmpty()) {
                    continue;
                }
                $this->printClientInfo($client);

                $filters = [
                    'limit_date_end' => (new DateTime('now'))->format('Y-m-d\TH:i:sP'),
                    'limit_date_start' => (new DateTime('-6 hours'))->format('Y-m-d\TH:i:sP'),
                ];
                $expiredTasks = $taskService->findTasksExpired($client, ['filters' => $filters]);
                if ($expiredTasks->isEmpty()) {
                    continue;
                }

                foreach ($automationsTasks as $automationTask) {
                    $appliedAutomationLogs = new Collection();
                    $this->printAutomationTaskInfo($automationTask);

                    resolve(LockHelper::class)->getLockByName($lockKey, 90);

                    foreach ($expiredTasks as $expiredTask) {
                        $logs = $automationTaskService->applyAfterTaskExpiration($automationTask, $expiredTask);
                        $appliedAutomationLogs = $appliedAutomationLogs->merge($logs);
                    }
                    $this->printAutomationLogsInfo($appliedAutomationLogs);
                }
            } catch (Exception $e) {
                dump($e);
                report($e);
            }
            $this->printSeparator();
            resolve(LockHelper::class)->getLockByName($lockKey, 90);
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function applyAutomationsTaskAfterTagStatusChange(Request $request)
    {
        $lockKey = 'applyAutomationsTaskAfterTagStatusChange';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 90)) {
            die('Locked');
        }

        $service = resolve(AutomationTaskService::class);
        $clients = resolve(ClientService::class)->findAllEnabled();

        foreach ($clients as $client) {
            SystemHelper::doFlush();

            try {
                $automationsTasks = $service->findEnabledAfterTagStatusChangeAutomationByClient($client);
                if ($automationsTasks->isEmpty()) {
                    continue;
                }
                $this->printClientInfo($client);

                foreach ($automationsTasks as $automationTask) {
                    if (!$service->isInHourToApply($automationTask)) {
                        continue;
                    }
                    $appliedAutomationLogs = new Collection();
                    $this->printAutomationTaskInfo($automationTask);

                    // @return Collection<leadId => Collection<EventLog>>
                    $eventLogsGroupedByLeadId = $service->findEventLogsEnabledToSendGroupedByLeadId($automationTask);

                    foreach ($eventLogsGroupedByLeadId as $leadId => $leadEventLogs) {
                        $logs = $service->applyAfterTagStatusChange($leadEventLogs, $automationTask);
                        $appliedAutomationLogs = $appliedAutomationLogs->merge($logs);
                    }
                    $this->printAutomationLogsInfo($appliedAutomationLogs);
                }
            } catch (Exception $e) {
                dump($e);
                report($e);
            }
            $this->printSeparator();
            resolve(LockHelper::class)->getLockByName($lockKey, 90);
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    private function printClientInfo(Client $client): void
    {
        WorkerOutputFormatter::heading("Client ID {$client->id}: {$client->name}", 3, ['margin_bottom' => 8]);
    }

    private function printAutomationLogsInfo(Collection $automationLogs): void
    {
        WorkerOutputFormatter::data(
            'Applied Logs',
            $automationLogs->map->only(['id', 'is_fully_applied'])->values(),
            [
                'indent' => 2,
                'emptyMessage' => 'No logs applied',
            ]
        );
    }

    private function printTriggeringLeadSalesInfo(Collection $triggeringLeadSales): void
    {
        WorkerOutputFormatter::data(
            'Lead Sales',
            $triggeringLeadSales->map->only(['id', 'description', 'sale_date'])->values(),
            [
                'indent' => 2,
                'emptyMessage' => 'No lead sales',
            ]
        );
    }

    private function printSeparator(): void
    {
        WorkerOutputFormatter::separator();
    }


    private function printAutomationTaskInfo(AutomationTask $automationTask): void
    {
        $data = $automationTask->only([
            'id',
            'create_hour',
            'trigger_type',
            'create_delay_days',
            'allowing_tags_ids',
            'tags_ids_to_assign',
            'allowing_status_ids',
            'triggering_tags_ids',
            'cancelling_tags_ids',
            'status_ids_to_assign',
            'triggering_status_ids',
            'cancelling_status_ids',
        ]);
        WorkerOutputFormatter::data(
            'Automation Task',
            $data,
            [
                'collapsed' => false,
                'indent' => 1,
            ]
        );
    }

}
