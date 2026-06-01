<?php

namespace App\Services\API;

use App\Models\Lead;
use Illuminate\Support\Collection;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\GmailMessagesLogService;
use App\DTO\GoogleAPI\GoogleAPIGmailMessageDTO;
use App\Services\API\Views\EmailService as ViewsEmailService;


class TimelineEventsService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $emailService;
    private $timelineEvents;
    private $eventsLogService;
    private $viewsEmailService;
    private $gmailMessagesLogService;


    public function __construct(
        ViewsEmailService $viewsEmailService,
        EventsLogService $eventsLogService,
        GmailMessagesLogService $gmailMessagesLogService,
        array $timelineEvents
    ) {
        $this->timelineEvents = $timelineEvents;
        $this->eventsLogService = $eventsLogService;
        $this->viewsEmailService = $viewsEmailService;
        $this->gmailMessagesLogService = $gmailMessagesLogService;
    }


    public function findTimelineEventsByLead(Lead $lead): Collection
    {
        $events = $this->eventsLogService->findEventsFromOneLead($lead, $this->timelineEvents);
        $events = $this->fillEmailsData($events);
        $gmailMsgs = $this->getGmailMessagesEvents($lead);
        $events = $events->merge($gmailMsgs);
        $events = $this->sortEvents($events);
        return $events;
    }


    protected function getGmailMessagesEvents(Lead $lead): Collection
    {
        $gmailEnabled = $lead->client->clientSettings->enable_google_gmail_api;
        if (!$gmailEnabled) {
            return new Collection([]);
        }

        $gmailMsgs = $this->gmailMessagesLogService->findByLead($lead, ['excludeFields' => ['body']]);
        
        $isUserScope = $lead->client->clientSettings->google_gmail_api_scope == 'user';
        if ($isUserScope) {
            $gmailMsgs = $gmailMsgs->filter(function ($dto) {
                $loginUser = $this->getUser();
                return $dto->clientyMetadata['user']['id'] == $loginUser->id;
            });
        }
        return $gmailMsgs;
    }


    protected function sortEvents(Collection $events): Collection
    {
        $filteredEvents = $events->filter(function ($doc) {
            if (is_a($doc, GoogleAPIGmailMessageDTO::class)) {
                return true;
            }
            return (is_array($doc) && $doc['event'] != 'lead_created' && $doc['event'] != 'lead_manually_created');
        });

        $sortedEvents = $filteredEvents->sort(function ($a, $b) {
            $aIsGmailDTO = is_a($a, GoogleAPIGmailMessageDTO::class);
            $bIsGmailDTO = is_a($b, GoogleAPIGmailMessageDTO::class);
            $aTs = $aIsGmailDTO ? $a->sentDate->getTimestamp() : $a['createdAtTs'];
            $bTs = $bIsGmailDTO ? $b->sentDate->getTimestamp() : $b['createdAtTs'];
            $aEvent = $aIsGmailDTO ? 'gmail_response' : $a['event'];
            $bEvent = $bIsGmailDTO ? 'gmail_response' : $b['event'];

            if (($aTs == $bTs) && $aEvent == 'lead_tag_deleted' && $bEvent == 'lead_tag_added') {
                return 1;
            }
            if (($aTs == $bTs) && $aEvent == 'whatsapp_sending_message_sent' && $bEvent == 'lead_tag_added') {
                return 1;
            }
            return ($aTs < $bTs) ? 1 : -1;
        });
        
        $createdEvent = $events->whereIn('event', ['lead_manually_created', 'lead_created'])->first();
        if ($createdEvent) {
            $sortedEvents->push($createdEvent);
        }
        return $sortedEvents;
    }


    protected function fillEmailsData(Collection $events): Collection
    {
        $emailEvents = $events->filter(function ($event) {
            return in_array(
                $event['event'],
                ['lead_email_sent', 'lead_email_scheduled', 'lead_email_opened', 'lead_email_cancelled']
            );
        });
        if ($emailEvents->isEmpty()) {
            return $events;
        }
        $emailIds = $emailEvents->pluck('log.email.id')->filter(fn ($emailId) => $emailId)->toArray();
        $emailModels = $this->viewsEmailService->findByIdsWithMailerInfo($emailIds);
        $events = $events->map(function ($event) use ($emailModels) {
            $inArray = in_array(
                $event['event'],
                ['lead_email_sent', 'lead_email_scheduled', 'lead_email_opened', 'lead_email_cancelled']
            );
            if ($inArray) {
                $emailModel = $emailModels->firstWhere('id', $event['log']['email']['id']);
                $event['log']['email']['emailModel'] = $emailModel;
            }
            return $event;
        });
        return $events;
    }

}
