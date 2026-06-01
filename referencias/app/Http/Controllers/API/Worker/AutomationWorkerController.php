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
use Illuminate\Database\Eloquent\Model;
use App\Models\AutomationEmailSendStep;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Automations\AutomationEmailSendService;
use App\Services\API\Automations\AutomationEmailSendStepService;
use App\Services\API\Automations\AutomationProposalResendService;
use App\Helpers\WorkerOutputFormatter;


class AutomationWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(500);
    }


    public function applyAutomationsEmailSendAfterSentProposal(Request $req)
    {
        $lockKey = 'applyAutomationsEmailSendAfterSentProposal';
        if (CronTabHelper::workerCronIsRunning($lockKey)) {
            die('Locked: cron is already runnning');
        }
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 900)) {
            die('Locked');
        }

        $clientId = $req->input('client_id');
        $clients = Client::where('enabled', true)->get();
        $service = resolve(AutomationEmailSendService::class);

        foreach ($clients as $client) {
            if ($clientId && $client->id != $clientId) {
                continue;
            }

            SystemHelper::doFlush();
            $this->printClientInfo($client);

            if ($client->clientSettings->email_sending_blocked) {
                $this->printSeparator();
                continue;
            }

            try {
                $automation = $service->findAfterSentProposalAutomationByClient($client);
                $this->printAutomationEmailSendInfo($automation);
                
                if ($automation) {
                    $appliedAutomationLogs = $service->apply($automation);
                    $this->printAutomationLogsInfo($appliedAutomationLogs);
                }
            } catch (Exception $e) {
                dump($e);
                report($e);
            }

            $this->printSeparator();
            resolve(LockHelper::class)->getLockByName($lockKey, 900);
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function applyAutomationsEmailSendAfterSale(Request $req)
    {
        $lockKey = 'applyAutomationsEmailSendAfterSale';
        if (CronTabHelper::workerCronIsRunning($lockKey)) {
            die('Locked: cron is already runnning');
        }
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 900)) {
            die('Locked');
        }

        $clientId = $req->input('client_id');
        $clients = Client::where('enabled', true)->get();
        $service = resolve(AutomationEmailSendService::class);

        foreach ($clients as $client) {
            if ($clientId && $client->id != $clientId) {
                continue;
            }

            SystemHelper::doFlush();
            $this->printClientInfo($client);

            if ($client->clientSettings->email_sending_blocked) {
                $this->printSeparator();
                continue;
            }

            try {
                $automation = $service->findAfterSaleAutomationByClient($client);
                $this->printAutomationEmailSendInfo($automation);
                
                if ($automation) {
                    $appliedAutomationLogs = $service->apply($automation);
                    $this->printAutomationLogsInfo($appliedAutomationLogs);
                }
            } catch (Exception $e) {
                dump($e);
                report($e);
            }

            $this->printSeparator();
            resolve(LockHelper::class)->getLockByName($lockKey, 900);
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function applyAutomationsEmailSendAfterTagsStatusChange(Request $req)
    {
        $lockKey = 'applyAutomationsEmailSendAfterTagsStatusChange';
        if (CronTabHelper::workerCronIsRunning($lockKey)) {
            die('Locked: cron is already runnning');
        }
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 900)) {
            die('Locked');
        }

        $clientId = $req->input('client_id');
        $clients = Client::where('enabled', true)->get();
        $service = resolve(AutomationEmailSendService::class);

        foreach ($clients as $client) {
            if ($clientId && $client->id != $clientId) {
                continue;
            }

            SystemHelper::doFlush();
            $this->printClientInfo($client);

            if ($client->clientSettings->email_sending_blocked) {
                $this->printSeparator();
                continue;
            }
            
            $automations = $service->findAfterTagsStatusChangeAutomationsByClient($client);
            $automations = $automations->filter(function ($automation) use ($service) {
                if (!$automation->enabled) {
                    return false;
                }
                $canRun = !$service->isWeekendAndCanNotRun($automation);
                return $canRun;
            });

            try {
                foreach ($automations as $automation) {
                    $this->printAutomationEmailSendInfo($automation);

                    // Se usa servicio para que busque en Redis cache
                    // $steps = $automation->automationEmailSendSteps;
                    $steps = resolve(AutomationEmailSendStepService::class)->findByAutomationEmailSend($automation);
                    foreach ($steps as $step) {
                        SystemHelper::doFlush();
                        if (!$service->isInHourToApply($step)) {
                            continue;
                        }

                        $this->printAutomationEmailSendStepInfo($step);

                        $eventLogs = $service->findEmailSendAfterTagStatusChangeEventLogs($step);
                        resolve(LockHelper::class)->getLockByName($lockKey, 900);

                        $enabledEventLogs = $eventLogs->get('enabledEventLogs');
                        $rejectedEventLogs = $eventLogs->get('rejectedEventLogs');

                        $nonAppliedAutomationLogs = $service->storeEmailSendAfterTagStatusChangeRejectedEventLogs(
                            $rejectedEventLogs, $step
                        );
                        $notifications = $service->storeNotEnabledUsersEmailSendNotificationsFromNonAppliedLogs(
                            $nonAppliedAutomationLogs, $step
                        );
                        resolve(LockHelper::class)->getLockByName($lockKey, 900);

                        $sentEmails = new Collection();
                        if ($enabledEventLogs->isNotEmpty()) {
                            $sentEmails = $service->sendEventLogsEmailsAndStoreLog($enabledEventLogs, $step);
                        }
                        resolve(LockHelper::class)->getLockByName($lockKey, 900);

                        $this->printNonAppliedAutomationLogsInfo($nonAppliedAutomationLogs);
                        $this->printSentEmailsInfo($sentEmails);
                    }
                    
                    resolve(LockHelper::class)->getLockByName($lockKey, 900);
                }
            } catch (Throwable $e) {
                dump($e);
                report($e);
            }
            
            $this->printSeparator();
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function applyAutomationsProposalResend(Request $req)
    {
        $lockKey = 'applyAutomationsProposalResend';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 900)) {
            die('Locked');
        }
        if (CronTabHelper::workerCronIsRunning($lockKey)) {
            die('Locked: cron is already runnning');
        }

        $clientId = $req->input('client_id');
        $clients = Client::where('enabled', true)->get();
        $service = resolve(AutomationProposalResendService::class);

        foreach ($clients as $client) {
            if ($clientId && $client->id != $clientId) {
                continue;
            }

            SystemHelper::doFlush();
            $this->printClientInfo($client);

            if ($client->clientSettings->email_sending_blocked) {
                $this->printSeparator();
                continue;
            }

            $automationProposalResendRule = $service->findRuleByClient($client);
            if ($automationProposalResendRule) {
                if (!$service->isInHourToApply($automationProposalResendRule)) {
                    continue;
                }
                
                $proposalEmails = $service->findSentProposals($automationProposalResendRule);
                foreach ($proposalEmails as $email) {
                    $appliedAutomationLog = $service->apply($email);
                    $this->printAutomationLogsInfo(collect([$appliedAutomationLog]));
                    
                    resolve(LockHelper::class)->getLockByName($lockKey, 900);
                }
            }
            $this->printSeparator();
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    private function printClientInfo(Client $client): void
    {
        WorkerOutputFormatter::heading("Client ID {$client->id}: {$client->name}", 3);
        if ($client->clientSettings->email_sending_blocked) {
            WorkerOutputFormatter::message('CLIENT EMAIL SENDING IS BLOCKED', 'error', ['indent' => 1]);
        }
    }


    private function printAutomationEmailSendInfo(?Model $automation): void
    {
        if (!$automation) {
            WorkerOutputFormatter::message('AutomationEmailSend: none found', 'muted', ['indent' => 1]);
            return;
        }

        WorkerOutputFormatter::data(
            'AutomationEmailSend',
            $automation->only(['id', 'trigger_type', 'name', 'enabled']),
            [
                'collapsed' => false,
                'indent' => 1,
            ]
        );
    }


    private function printAutomationEmailSendStepInfo(AutomationEmailSendStep $step): void
    {
        WorkerOutputFormatter::data(
            'AutomationEmailSendStep',
            $step->only(['id', 'send_hour', 'send_delay_days', 'send_delay_minutes']),
            [
                'collapsed' => false,
                'indent' => 2,
            ]
        );
    }


    private function printAutomationLogsInfo(Collection $automationLogs): void
    {
        WorkerOutputFormatter::data(
            'Applied Logs',
            $automationLogs->pluck('id')->values(),
            [
                'indent' => 2,
                'emptyMessage' => 'No logs applied',
            ]
        );
    }


    private function printNonAppliedAutomationLogsInfo(Collection $nonAppliedAutomationLogs): void
    {
        if ($nonAppliedAutomationLogs->isEmpty()) {
            return;
        }
        WorkerOutputFormatter::data(
            'Non applied Logs',
            $nonAppliedAutomationLogs->pluck('id')->values(),
            [
                'indent' => 2,
                'collapsed' => false,
            ]
        );
    }


    private function printSentEmailsInfo(Collection $sentEmails): void
    {
        WorkerOutputFormatter::data(
            'Sent Emails',
            $sentEmails->pluck('id')->values(),
            [
                'indent' => 2,
                'emptyMessage' => 'No emails sent',
            ]
        );
    }


    private function printSeparator(): void
    {
        WorkerOutputFormatter::separator();
    }

}
