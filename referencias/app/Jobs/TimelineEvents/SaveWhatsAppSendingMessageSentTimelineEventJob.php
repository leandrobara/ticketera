<?php

namespace App\Jobs\TimelineEvents;

use DateTime;
use Throwable;
use Illuminate\Bus\Queueable;
use App\Models\WhatsAppSendingMessage;
use App\Services\API\EventsLogService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveWhatsAppSendingMessageSentTimelineEventJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    

    public function __construct(
        public readonly int $whatsAppSendingMessageId,
        public readonly ?DateTime $eventLogDate = null
    ) {
    }


    public function handle()
    {
        $whatsAppSendingMessage = WhatsAppSendingMessage::findOrFail($this->whatsAppSendingMessageId);
        resolve(EventsLogService::class)->saveWhatsAppSendingMessageSentTimelineEvent(
            $whatsAppSendingMessage, $this->eventLogDate
        );
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}


