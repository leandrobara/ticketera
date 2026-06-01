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
use App\Services\API\Automations\AutomationProposalModifyLeadAfterSendService;


class EmailSentJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $email;
    public $emailId;
    

    public function __construct(int $emailId)
    {
        $this->emailId = $emailId;
    }


    public function handle()
    {
        $automationLog = $this->applyAfterSendProposalAutomation();
        if ($automationLog) {
            $this->notifyLeadModifiedWithBroadcast();
        }
    }


    public function applyAfterSendProposalAutomation(): ?AutomationLog
    {

        $this->email = Email::findOrFail($this->emailId);
        // It is a sent proposal (sent by an user, not by some system event)
        if ($this->email->is_proposal && !$this->email->automation_log_id) {
            // Hago esto para evitar que un mismo email enviado varias veces al mismo lead llegue acá
            // y termine compitiendo en paralelo por las transacciones de la BD provocando un error de lock.
            $rand = mt_rand(1, 1000000);
            usleep($rand);
            $lead = $this->email->lead;

            $key = 'EmailSentJob:applyAfterSendProposalAutomation:lead_id:' . $lead->id;
            $lockIsGranted = resolve(LockHelper::class)->getLockByName($key, 3);
            if (!$lockIsGranted) {
                return null;
            }

            $service = resolve(AutomationProposalModifyLeadAfterSendService::class);
            $automationLog = $service->apply($lead);
            return $automationLog;
        }
        return null;
    }


    public function notifyLeadModifiedWithBroadcast()
    {
        $lead = $this->email->lead->fresh();
        $browserEventsDispatcher = resolve(BrowserEventsDispatcher::class);
        $browserEventsDispatcher->notifyLeadTagsModified($lead);
        $browserEventsDispatcher->notifyLeadStatusModified($lead);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
        $this->getErrorLog()->error(PHP_EOL . PHP_EOL);
    }


    protected function logStartMessage()
    {
        $this->getInfoLog()->info('STARTING ' . self::class . ' ...');
        $this->getInfoLog()->info('- Email ID: ' . $this->email->id);
    }


    protected function logEndMessage()
    {
        $this->getInfoLog()->info('ENDED ' . self::class . PHP_EOL);
    }

}
