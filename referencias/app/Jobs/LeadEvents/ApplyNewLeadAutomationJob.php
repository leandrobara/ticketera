<?php

namespace App\Jobs\LeadEvents;

use DateTime;
use Throwable;
use Exception;
use App\Models\Lead;
use App\Helpers\LockHelper;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use App\Helpers\ClientyMailerAPIHelper;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\LeadEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\Notifications\NotificationService;
use App\Services\API\Automations\AutomationNewLeadService;
use App\Services\API\Dispatchers\LeadEventsDispatcherService;


class ApplyNewLeadAutomationJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $timeout = 30;
    public $backoff = 50;

    public $leadId;
    public $logUuid;


    public function __construct(int $leadId)
    {
        $this->leadId = $leadId;
        $this->logUuid = Str::orderedUuid();
    }


    public function handle()
    {
        $lockKey = 'ApplyNewLeadAutomationJob:handle:leadId:' . $this->leadId;

        $this->initializeLogData($this->leadId, $lockKey);

        $lockIsGranted = resolve(LockHelper::class)->getLockByName($lockKey, 3);
        if (!$lockIsGranted) {
            $this->logInfo("- LOCK NOT GRANTED [{$lockKey}]");
            resolve(LockHelper::class)->releaseLockByName($lockKey);
            return true;
        }
        $this->logInfo("- LOCK GRANTED [{$lockKey}]");

        $lead = Lead::findOrFail($this->leadId);

        // Esto evita que corra en paralelo cuando es un bulk upload, ya que para asignar usuarios rotativamente
        // depende de que haya terminado el automation new lead anterior.
        $clientId = $lead->client_id;
        $bulkLockKey = $lead->is_bulk_created ? "ApplyNewLeadAutomationJob:handle:bulkLead:{$clientId}" : null;
        if ($bulkLockKey) {
            $bulkLockIsGranted = resolve(LockHelper::class)->getLockByName($bulkLockKey, 1);
            if (!$bulkLockIsGranted) {
                $this->logInfo("- BULK LOCK NOT GRANTED [{$bulkLockKey}]: JOB REQUEUED");
                resolve(LockHelper::class)->releaseLockByName($lockKey);
                $this->requeueThisJob($lead);
                return true;
            }
            $this->logInfo("- BULK LOCK GRANTED [{$bulkLockKey}]");
        }

        // Remember: automation log is ONLY saved if there is match between automation and lead.
        $service = resolve(AutomationNewLeadService::class);
        $appliedAutomations = $service->apply($lead);
        if ($appliedAutomations->isEmpty()) {
            $this->logInfo('- No applied NewLeadAutomations');
        } else {
            $idsStr = $appliedAutomations->implode('id', ', ');
            $this->logInfo("- Applied NewLeadAutomations: [{$idsStr}]");
        }

        if ($bulkLockKey) {
            resolve(LockHelper::class)->releaseLockByName($bulkLockKey);
        }
    }


    protected function initializeLogData(
        int $leadId,
        string $lockKey,
    ): void {
        $this->logInfo('');
        $this->logInfo('----------------------------------------------------------');
        $this->logInfo('STARTING ' . self::class . ' ...');
        $this->logInfo('- leadId: ' . $leadId);
        $this->logInfo('- lockKey: ' . $lockKey);
        $this->logInfo('- attempt: ' . $this->attempts());
    }


    protected function requeueThisJob(Lead $lead): void
    {
        $delaySecs = mt_rand(10, 200) / 100; // Entre 0.10 y 2.00
        $this->logInfo("- delaySecs: {$delaySecs}");
        resolve(LeadEventsDispatcherService::class)->dispatchApplyNewLeadAutomationJob($lead, $delaySecs);
        // Delete the current job
        $this->delete();
    }


    protected function logException(Throwable $e, string $logType = 'info')
    {
        $logFunction = $logType === 'error'
            ? fn($msg) => $this->getErrorLog()->error($msg)
            : fn($msg) => $this->logInfo($msg)
        ;
    
        if ($logType === 'error') {
            $dateStr = '[' . now()->format('Y-m-d H:i:s') . '] ';
            $logFunction($dateStr);
        }
        
        $logFunction($e->getMessage());

        $trace = collect($e->getTrace())->take(10);
        foreach ($trace as $traceEntry) {
            $logFunction($this->formatTraceEntry($traceEntry));
        }
    }


    protected function formatTraceEntry(array $traceEntry): string
    {
        return sprintf(
            '%s: %s(%s)', $traceEntry['file'] ?? 'N/A', $traceEntry['function'] ?? 'N/A', $traceEntry['line'] ?? 'N/A'
        );
    }


    public function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }


    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
        $this->getErrorLog()->error($e);
    }

}
