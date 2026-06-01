<?php

namespace App\Http\Controllers\API\Worker;

use Throwable;
use Exception;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Helpers\CronTabHelper;
use Illuminate\Support\Collection;
use App\Models\WAutomationSequence;
use App\Services\API\ClientService;
use Illuminate\Support\Facades\Log;
use App\Models\WAutomationSequenceStep;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WAutomations\WAutomationSequenceService;
use App\Services\API\WAutomations\WAutomationSequenceStepService;
use App\Services\API\WAutomations\WAutomationProposalResendService;
use App\Helpers\WorkerOutputFormatter;


class WAutomationWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(500);
    }


    public function applyWAutomationsProposalResend(Request $req)
    {
        $lockKey = 'applyWAutomationsProposalResend';
        if (CronTabHelper::workerCronIsRunning($lockKey)) {
            die('Locked: cron is already runnning');
        }
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 90)) {
            die('Locked');
        }

        $clientId = $req->input('client_id');
        $service = resolve(WAutomationProposalResendService::class);
        $clients = resolve(ClientService::class)->findWithEnabledWAPIOrWapSenderJob();

        foreach ($clients as $client) {
            if ($clientId && $client->id != $clientId) {
                continue;
            }

            SystemHelper::doFlush();
            $this->printClientInfo($client);

            $wAutomationProposalResendRule = $service->findRuleByClient($client);
            if ($wAutomationProposalResendRule) {
                if (!$service->isInHourToApply($wAutomationProposalResendRule)) {
                    continue;
                }

                $proposalWapSendings = $service->findSentProposals($wAutomationProposalResendRule);
                foreach ($proposalWapSendings as $whatsAppSending) {
                    try {
                        $appliedWAutomationLogs = $service->apply($whatsAppSending);
                        $this->printWAutomationLogsInfo($appliedWAutomationLogs);
                    } catch (Throwable $e) {
                        dump($e);
                        report($e);
                        continue;
                    }
                    
                    resolve(LockHelper::class)->getLockByName($lockKey, 90);
                }
            }
            $this->printSeparator();
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function applyWAutomationsSequenceAfterSentProposal(Request $req)
    {
        $lockKey = 'applyWAutomationsSequenceAfterSentProposal';
        if (CronTabHelper::workerCronIsRunning($lockKey)) {
            die('Locked: cron is already runnning');
        }
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 90)) {
            die('Locked');
        }

        $wAutSequenceService = resolve(WAutomationSequenceService::class);
        $wAutSequenceStepService = resolve(WAutomationSequenceStepService::class);

        $clientId = $req->input('client_id');
        $clients = resolve(ClientService::class)->findWithEnabledWAPIOrWapSenderJob();
        foreach ($clients as $client) {
            if ($clientId && $client->id != $clientId) {
                continue;
            }

            SystemHelper::doFlush();
            $this->printClientInfo($client);

            try {
                $wAutSequence = $wAutSequenceService->findAfterSentProposalWAutomationByClient($client);
                $this->printWAutomationSequenceInfo($wAutSequence);
                if (!$wAutSequence->enabled || $wAutSequenceService->isWeekendAndCanNotRun($wAutSequence)) {
                    continue;
                }

                // Se usa servicio para que busque en Redis cache
                // $wAutSequenceSteps = $wAutSequence->wAutomationSequenceSteps;
                $wAutSequenceSteps = $wAutSequenceStepService->findByWAutomationSequence($wAutSequence);
                foreach ($wAutSequenceSteps as $wAutSequenceStep) {
                    if (!$wAutSequenceService->isInHourToApply($wAutSequenceStep)) {
                        continue;
                    }

                    $triggeringWapSendingProposals = $wAutSequenceService->findSentWapSendingProposalsEnabledToSend(
                        $wAutSequence, $wAutSequenceStep
                    );

                    $this->printWAutomationSequenceStepInfo($wAutSequenceStep);

                    foreach ($triggeringWapSendingProposals as $triggeringWapSendingProposal) {
                        $appliedWAutomationLogs = $wAutSequenceService->applyAfterSentProposal(
                            $triggeringWapSendingProposal, $wAutSequenceStep
                        );
                        $this->printWAutomationLogsInfo($appliedWAutomationLogs);
                    }
                }
            } catch (Throwable $e) {
                dump($e);
                report($e);
            }
            $this->printSeparator();
            resolve(LockHelper::class)->getLockByName($lockKey, 90);
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function applyWAutomationsSequenceAfterSale(Request $req)
    {
        $lockKey = 'applyWAutomationsSequenceAfterSale';
        if (CronTabHelper::workerCronIsRunning($lockKey)) {
            die('Locked: cron is already runnning');
        }
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 90)) {
            die('Locked');
        }

        $wAutSequenceService = resolve(WAutomationSequenceService::class);
        $wAutSequenceStepService = resolve(WAutomationSequenceStepService::class);


        $clientId = $req->input('client_id');
        $clients = resolve(ClientService::class)->findWithEnabledWAPIOrWapSenderJob();
        foreach ($clients as $client) {
            if ($clientId && $client->id != $clientId) {
                continue;
            }

            SystemHelper::doFlush();
            $this->printClientInfo($client);

            try {
                $wAutSequence = $wAutSequenceService->findAfterSaleWAutomationByClient($client);
                $this->printWAutomationSequenceInfo($wAutSequence);
                if (!$wAutSequence->enabled || $wAutSequenceService->isWeekendAndCanNotRun($wAutSequence)) {
                    continue;
                }

                // Se usa servicio para que busque en Redis cache
                // $wAutSequenceSteps = $wAutSequence->wAutomationSequenceSteps;
                $wAutSequenceSteps = $wAutSequenceStepService->findByWAutomationSequence($wAutSequence);
                foreach ($wAutSequenceSteps as $wAutSequenceStep) {
                    if (!$wAutSequenceService->isInHourToApply($wAutSequenceStep)) {
                        continue;
                    }

                    $triggeringLeadSales = $wAutSequenceService->findLeadSalesEnabledToSend(
                        $wAutSequence, $wAutSequenceStep
                    );
                    $this->printWAutomationSequenceStepInfo($wAutSequenceStep);

                    foreach ($triggeringLeadSales as $triggeringLeadSale) {
                        $appliedWAutomationLogs = $wAutSequenceService->applyAfterSale(
                            $triggeringLeadSale, $wAutSequenceStep
                        );
                        $this->printWAutomationLogsInfo($appliedWAutomationLogs);
                    }
                }
            } catch (Throwable $e) {
                dump($e);
                report($e);
            }
            $this->printSeparator();
            resolve(LockHelper::class)->getLockByName($lockKey, 90);
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function applyWAutomationsSequenceAfterTagStatusChange(Request $req)
    {
        $infoLog = Log::channel('WAutomationWorkerControllerSequenceAfterTagStatusChangeLog');
        $infoLog->info("\n---------------------------------------------Starting\n");

        $lockKey = 'applyWAutomationsSequenceAfterTagStatusChange';
        if (CronTabHelper::workerCronIsRunning($lockKey)) {
            $infoLog->info('[Locked: cron is already runnning]');
            die('Locked: cron is already runnning');
        }
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 90)) {
            $infoLog->info('[Locked]');
            die('Locked');
        }

        $wAutSequenceService = resolve(WAutomationSequenceService::class);
        $wAutSequenceStepService = resolve(WAutomationSequenceStepService::class);

        $clientId = $req->input('client_id');
        $clients = resolve(ClientService::class)->findWithEnabledWAPIOrWapSenderJob();
        foreach ($clients as $client) {
            if ($clientId && $client->id != $clientId) {
                continue;
            }
            
            SystemHelper::doFlush();
            $this->printClientInfo($client);
            $infoLog->info("\n-------\nClient ID: {$client->id}");

            try {
                $wAutSequences = $wAutSequenceService->findAfterTagStatusChangeWAutomationByClient($client);
                $infoLog->info("\n----wAutSequences IDs", $wAutSequences->pluck('id')->toArray());

                foreach ($wAutSequences as $wAutSequence) {
                    $infoLog->info("wAutSequence ID: {$wAutSequence->id}");

                    $this->printWAutomationSequenceInfo($wAutSequence);
                    if (!$wAutSequence->enabled) {
                        $infoLog->info("[wAutSequence Not enabled]");
                        continue;
                    }
                    if ($wAutSequenceService->isWeekendAndCanNotRun($wAutSequence)) {
                        $infoLog->info("[wAutSequence isWeekendAndCanNotRun]");
                        continue;
                    }

                    // Se usa servicio para que busque en Redis cache
                    // $wAutSequenceSteps = $wAutSequence->wAutomationSequenceSteps;
                    $wAutSequenceSteps = $wAutSequenceStepService->findByWAutomationSequence($wAutSequence);
                    $infoLog->info("\n----------wAutSequenceSteps IDs", $wAutSequenceSteps->pluck('id')->toArray());

                    foreach ($wAutSequenceSteps as $wAutSequenceStep) {
                        $infoLog->info("wAutSequenceStep ID: {$wAutSequenceStep->id}");
                        if (!$wAutSequenceService->isInHourToApply($wAutSequenceStep)) {
                            $infoLog->info("[wAutSequenceStep is NOT InHourToApply]");
                            continue;
                        }
                        $this->printWAutomationSequenceStepInfo($wAutSequenceStep);
                        
                        // @return Collection<leadId => Collection<EventLog>>
                        $eventLogsGroupedByLead = $wAutSequenceService->findEventLogsEnabledToSendGroupedByLeadId(
                            $wAutSequence, $wAutSequenceStep
                        );
                        $infoLog->info(
                            "\n----------------eventLogsGroupedByLead count: {$eventLogsGroupedByLead->count()}"
                        );

                        foreach ($eventLogsGroupedByLead as $leadId => $leadEventLogs) {
                            $infoLog->info("leadId: {$leadId}");
                            $infoLog->info("leadEventLogs: ", $leadEventLogs->toArray());
                            $appliedWAutomationLogs = $wAutSequenceService->applyAfterTagStatusChange(
                                $leadId, $leadEventLogs, $wAutSequenceStep
                            );

                            if ($appliedWAutomationLogs->isNotEmpty()) {
                                $infoLog->info("appliedWAutomationLogs: ", $appliedWAutomationLogs->toArray());
                            } else {
                                $infoLog->info("appliedWAutomationLogs: [ALREADY APPLIED]");
                            }
                            $this->printWAutomationLogsInfo($appliedWAutomationLogs);
                        }
                    }
                }
            } catch (Throwable $e) {
                dump($e);
                $infoLog->info("ERROR: " . (string) $e);
                report($e);
            }
            $this->printSeparator();
            resolve(LockHelper::class)->getLockByName($lockKey, 90);
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    private function printSeparator(): void
    {
        WorkerOutputFormatter::separator();
    }

    private function printClientInfo(Client $client): void
    {
        WorkerOutputFormatter::heading("Client ID {$client->id}: {$client->name}", 3);
    }

    private function printWAutomationLogsInfo(Collection $automationLogs): void
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

    private function printWAutomationSequenceInfo(?Model $wAutomation): void
    {
        if ($wAutomation) {
            $data = $wAutomation->only(['id', 'trigger_type', 'name', 'enabled']);
            WorkerOutputFormatter::data(
                'WAutomationSequence',
                $data,
                [
                    'collapsed' => false,
                    'indent' => 1,
                ]
            );
            return;
        }
        WorkerOutputFormatter::message('WAutomationSequence: none found', 'muted', ['indent' => 1]);
    }

    private function printWAutomationSequenceStepInfo(WAutomationSequenceStep $step): void
    {
        $data = $step->only(['id', 'send_hour', 'send_delay_days', 'send_delay_minutes']);
        WorkerOutputFormatter::data(
            'WAutomationSequenceStep',
            $data,
            [
                'collapsed' => false,
                'indent' => 2,
            ]
        );
    }

}
