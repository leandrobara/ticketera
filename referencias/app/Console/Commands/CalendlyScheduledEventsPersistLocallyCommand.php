<?php

namespace App\Console\Commands;

use DateTime;
use Exception;
use Illuminate\Console\Command;
use App\Helpers\CalendlyAPIHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use App\Models\MongoDB\CalendlyScheduledEvent;
use App\Services\API\CalendlyScheduledEventService;


class CalendlyScheduledEventsPersistLocallyCommand extends Command
{

    protected $description = 'Get scheduled events from Calendly API and persist them in MongoDB';
    protected $signature = 'calendly-scheduled-events:persist-locally ' .
        '{--days-ago-start=} {--date-start=} {--date-end=}'
    ;


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $dateEndStr = $this->option('date-end') ?? null;
        $dateStartStr = $this->option('date-start') ?? null;
        $daysAgoStart = (int) ($this->option('days-ago-start') ?? 0);
        $dateEnd = $dateEndStr ? new DateTime($dateEndStr) : null;
        $dateStart = $dateStartStr ? new DateTime($dateStartStr) : null;
        if ($daysAgoStart) {
            $dateStart = new DateTime("{$daysAgoStart} days ago");
        }

        $calendlyAPIHelper = resolve(CalendlyAPIHelper::class);
        $scheduledEventService = resolve(CalendlyScheduledEventService::class);

        $params = [
            'count' => 100,
            'sort' => 'start_time:asc',
            'date_end' => $dateEnd ?? null,
            'date_start' => $dateStart ?? null,
        ];
        $response = $calendlyAPIHelper->listPaginatedScheduledEvents($params);
        $pagination = $response['pagination'];
        $scheduledEvents = $response['scheduledEvents'];
        $nextPageToken = $pagination['next_page_token'] ?? null;

        while ($nextPageToken) {
            foreach ($scheduledEvents as $i => $scheduledEventData) {
                $eventUUID = basename($scheduledEventData['uri']);

                $existentEvent = $scheduledEventService->findOneByUri($scheduledEventData['uri']);
                if ($existentEvent) {
                    $this->printExistentEventMsg($existentEvent);
                    continue;
                }

                try {
                    $scheduledEventData['invitees'] = $calendlyAPIHelper->listEventInvites($eventUUID);
                } catch (Exception $e) {
                    $this->info('listEventInvites() API ERROR: SLEEPING 5 SECONDS BEFORE TRYING AGAIN');
                    sleep(5);
                    $scheduledEventData['invitees'] = $calendlyAPIHelper->listEventInvites($eventUUID);
                }

                $persistedEvent = $scheduledEventService->create($scheduledEventData);
                $this->printPersistedMsg($persistedEvent);
            }

            $params['page_token'] = $nextPageToken;
            try {
                $response = $calendlyAPIHelper->listPaginatedScheduledEvents($params, $nextPageToken);
            } catch (Exception $e) {
                $this->info('listPaginatedScheduledEvents() API ERROR: SLEEPING 5 SECONDS BEFORE TRYING AGAIN');
                sleep(5);
                $response = $calendlyAPIHelper->listPaginatedScheduledEvents($params, $nextPageToken);
            }

            $pagination = $response['pagination'];
            $scheduledEvents = $response['scheduledEvents'];
            $nextPageToken = $pagination['next_page_token'] ?? null;
        }
    }

    
    private function printPersistedMsg(CalendlyScheduledEvent $scheduledEvent): void
    {
        $msg = "- Persisted event | URI: {$scheduledEvent->uri} | Date: {$scheduledEvent->created_at}";
        $this->info($msg);
    }


    private function printExistentEventMsg(CalendlyScheduledEvent $scheduledEvent): void
    {
        $msg = "- Existent event | URI: {$scheduledEvent->uri} | Date: {$scheduledEvent->created_at}";
        $this->error($msg);
    }

}
