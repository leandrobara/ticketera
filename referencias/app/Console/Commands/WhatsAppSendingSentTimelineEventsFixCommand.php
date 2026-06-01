<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppSending;
use Illuminate\Console\Command;
use App\Models\WhatsAppSendingMessage;
use App\Services\API\EventsLogService;
use Illuminate\Support\Facades\Artisan;


class WhatsAppSendingSentTimelineEventsFixCommand extends Command
{

    protected $cachedUserIds = [];
    protected $cachedLeadIds = [];
    protected $signature = 'wap-sendings:fix-sent-timeline-events';
    protected $description = 'Fix WhatsApp Sendings Messages timeline events';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $eventsLogService = resolve(EventsLogService::class);
        $wapSentMsgs = WhatsAppSendingMessage::whereNotNull('sent_date')->whereNotNull('lead_id')->get();
        foreach ($wapSentMsgs as $wapSendingMsg) {
            $lead = Lead::find($wapSendingMsg->lead_id);
            if (!$lead) {
                continue;
            }
            $events = $eventsLogService->findEventsFromOneLead($lead, ['whatsapp_sending_message_sent']);
            $event = $events->where('log.whatsAppSendingMessage.id', $wapSendingMsg->id)->first();
            if ($event) {
                $this->info("- WAP Sending Msg ID: {$wapSendingMsg->id}. Lead ID: {$lead->id}. Event already EXISTS.");
                continue;
            }

            $eventLogDate = $wapSendingMsg->sent_date->toDateTime();
            $eventsLogService->saveWhatsAppSendingMessageSentTimelineEvent($wapSendingMsg, $eventLogDate);
            $this->info("- WAP Sending Msg ID: {$wapSendingMsg->id}. Lead ID: {$lead->id}. New event log CREATED.");
        }
    }


}
