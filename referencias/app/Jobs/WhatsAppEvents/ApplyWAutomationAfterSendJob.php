<?php

namespace App\Jobs\WhatsAppEvents;

use Throwable;
use Exception;
use App\Helpers\LockHelper;
use Illuminate\Bus\Queueable;
use App\Models\WAutomationAfterSend;
use Illuminate\Queue\SerializesModels;
use App\Models\WhatsAppSendingMessage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\WhatsAppEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\Dispatchers\BrowserEventsDispatcher;
use App\Services\API\WAutomations\WAutomationAfterSendService;


class ApplyWAutomationAfterSendJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;


    public function __construct(protected readonly int $wapSendingMsgId)
    {
    }


    public function handle()
    {
        $lockKey = 'ApplyWAutomationAfterSendJob:wapSendingMsgId:' . $this->wapSendingMsgId;
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($lockKey, 3);
        if (!$lockIsGranted) {
            return null;
        }

        $wapSendingMsg = WhatsAppSendingMessage::findOrFail($this->wapSendingMsgId);
        $appliedWAutLog = resolve(WAutomationAfterSendService::class)->apply($wapSendingMsg);
        if ($appliedWAutLog) {
            $lead = $wapSendingMsg->lead->fresh();
            $browserEventsDispatcher = resolve(BrowserEventsDispatcher::class);
            $browserEventsDispatcher->notifyLeadTagsModified($lead);
            $browserEventsDispatcher->notifyLeadStatusModified($lead);
        }
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
