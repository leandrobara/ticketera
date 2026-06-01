<?php

namespace App\Console\Commands;

use DateTime;
use Exception;
use Illuminate\Console\Command;
use App\Models\LeadContactEmail;
use App\Helpers\CalendlyAPIHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use App\Models\MongoDB\CalendlyScheduledEvent;
use App\Services\API\CalendlyScheduledEventService;


class CalendlyScheduledEventsSetClientyLeadIdCommand extends Command
{

    protected $signature = 'calendly-scheduled-events:set-clienty-lead-id ' .
        '{--days-ago-start=} {--date-start=} {--date-end=}'
    ;
    protected $description = 'Set clientyLeadId in Calendly stored scheduled events';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $index = 0;
        $offset = 0;
        $limit = 100;
        $queryBuilder = CalendlyScheduledEvent::limit($limit);
        $events = (clone $queryBuilder)->offset($offset)->get();

        while ($events->isNotEmpty()) {
            foreach ($events as $scheduledEvent) {
                $index++;

                if ($scheduledEvent->clientyLeadIds) {
                    $this->printClientyLeadIdsAlreadySettedMsg($index, $scheduledEvent);
                    continue;
                }

                $eventEmailStr = $scheduledEvent->invitees[0]['email'] ?? null;
                if (!$eventEmailStr) {
                    $this->printNonExistentEventEmailMsg($index, $scheduledEvent);
                    continue;
                }

                $emailHash = LeadContactEmail::buildHash($eventEmailStr);
                $leadContactEmails = LeadContactEmail::where('hash', $emailHash)->where('client_id', 2)->get();
                if ($leadContactEmails->isEmpty()) {
                    $this->printNonExistentClientyLeadEmailMsg($index, $scheduledEvent);
                    continue;
                }
                
                $clientyLeadIds = $leadContactEmails->pluck('lead_id')->unique()->toArray();
                $scheduledEvent->clientyLeadIds = $clientyLeadIds;
                $scheduledEvent->save();
                $this->printSuccessMatchMsg($index, $scheduledEvent, $clientyLeadIds);
            }

            $offset = $offset + $limit;
            $events = (clone $queryBuilder)->offset($offset)->get();
        }
    }


    private function printClientyLeadIdsAlreadySettedMsg(int $index, CalendlyScheduledEvent $scheduledEvent): void
    {
        $msg = "{$index} - clientyLeadIds already setted | hash: {$scheduledEvent->hash}";
        $this->info($msg);
    }


    private function printNonExistentEventEmailMsg(int $index, CalendlyScheduledEvent $scheduledEvent): void
    {
        $msg = "{$index} - Event with NO email | hash: {$scheduledEvent->hash}";
        $this->error($msg);
    }


    private function printNonExistentClientyLeadEmailMsg(int $index, CalendlyScheduledEvent $scheduledEvent): void
    {
        $msg = "{$index} - Event email does not exist in Clienty | hash: {$scheduledEvent->hash}";
        $msg .= " | email: {$scheduledEvent->invitees[0]['email']}";
        $this->error($msg);
    }


    private function printSuccessMatchMsg(
        int $index,
        CalendlyScheduledEvent $scheduledEvent,
        array $clientyLeadIds
    ) {
        $clientyLeadIdsStr = implode(', ', $clientyLeadIds);
        $msg = "{$index} - Event mached successfully | hash: {$scheduledEvent->hash}";
        $msg .= " | email: {$scheduledEvent->invitees[0]['email']}";
        $msg .= " | clientyLeadIds {$clientyLeadIdsStr}";
        $this->info($msg);
    }

}
