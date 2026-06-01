<?php

namespace App\Jobs\EmailEvents;

use Throwable;
use App\Models\Email;
use App\Helpers\LockHelper;
use App\Models\AutomationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\EmailEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\Dispatchers\BrowserEventsDispatcher;
use App\Services\API\Automations\AutomationProposalInteractionService;


class EmailOpenedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $openedEmail;
    public $openedEmailId;
    

    public function __construct(int $openedEmailId)
    {
        $this->openedEmailId = $openedEmailId;
    }


    public function handle()
    {
        $this->logStartMessage();

        usleep(mt_rand(70000, 200000)); // To avoid race condition
        // Lock por email ID
        $key = 'EmailOpenedJob:handle:openedEmailId:' . $this->openedEmailId;
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($key, 3);
        if (!$lockIsGranted) {
            return null;
        }
        
        $this->openedEmail = Email::findOrFail($this->openedEmailId);
        $automationLog = $this->applyProposalOpenTriggerAutomation();
        if ($automationLog) {
            $this->notifyLeadTagsModifiedWithBroadcast();
        }

        $this->logEndMessage();
    }


    public function applyProposalOpenTriggerAutomation(): ?AutomationLog
    {
        if ($this->openedEmail->is_proposal) {
            // Para evitar Error 1062 de BD al actualizar tags, debido a race condition
            // al recibir un open paralelo de distinto emailId pero MISMO Lead.
            $keyByLead = 'EmailOpenedJob:handle:openedEmail->lead_id:' . $this->openedEmail->lead_id;
            $lockIsGrantedByLead = resolve(LockHelper::class)->getLockByName($keyByLead, 3);
            if (!$lockIsGrantedByLead) {
                $rand = mt_rand(1000000, 2000000);
                usleep($rand);
            }

            $service = resolve(AutomationProposalInteractionService::class);
            $automationLog = $service->applyOpenTrigger($this->openedEmail);
            return $automationLog;
        }
        return null;
    }


    public function notifyLeadTagsModifiedWithBroadcast()
    {
        $browserEventsDispatcher = resolve(BrowserEventsDispatcher::class);
        $browserEventsDispatcher->notifyLeadTagsModified($this->openedEmail->lead->fresh());
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
        $this->getErrorLog()->error(PHP_EOL . PHP_EOL);
    }


    protected function logStartMessage()
    {
        $this->getInfoLog()->info('STARTING ' . self::class . ' ...');
        $this->getInfoLog()->info('- Email ID: ' . $this->openedEmailId);
    }


    protected function logEndMessage()
    {
        $this->getInfoLog()->info('ENDED ' . self::class . PHP_EOL);
    }

}
