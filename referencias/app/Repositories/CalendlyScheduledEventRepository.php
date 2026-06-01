<?php

namespace App\Repositories;

use DateTime;
use Exception;
use MongoDB\BSON\Regex;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Eloquent\Builder;
use App\Models\MongoDB\CalendlyScheduledEvent;
use App\Repositories\Criteria\Sort\MongoSortCriteria;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class CalendlyScheduledEventRepository
{

    public function findOneByUri(string $uri): ?CalendlyScheduledEvent
    {
        $scheduledEvent = CalendlyScheduledEvent::where('uri', $uri)->limit(1)->first();
        return $scheduledEvent;
    }


    public function create(array $calendlyScheduledEventData): CalendlyScheduledEvent
    {
        $calendlyScheduledEvent = new CalendlyScheduledEvent($calendlyScheduledEventData);
        $calendlyScheduledEvent->hash = $calendlyScheduledEvent->buildHash();
        $this->validateStoreData($calendlyScheduledEvent);

        $calendlyScheduledEvent->save();
        return $calendlyScheduledEvent->fresh();
    }


    public function findByLeadId(int $leadId): Collection
    {
        return CalendlyScheduledEvent::where('clientyLeadIds', $leadId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get(['name', 'invitees', 'clientyLeadIds'])
        ;
    }


    public function findByLeadEmail(string $leadEmail, $opts = []): Collection
    {
        $limit = $opts['limit'] ?? 20;
        //case insensitive regex for the lead email: JOHN@EXAMPLE.COM or john@example.com
        $leadEmailRegex = new Regex($leadEmail, 'i');
        $events = CalendlyScheduledEvent::where('invitees.email', $leadEmailRegex)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
        ;
        return $events;
    }


    public function findByLeadName(string $leadName, $opts = []): Collection
    {
        $limit = $opts['limit'] ?? 20;
        //case insensitive regex for the lead name: JOHN or john
        $leadNameRegex = new Regex($leadName, 'i');
        $events = CalendlyScheduledEvent::where('invitees.name', 'regexp', $leadNameRegex)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['name', 'invitees', 'clientyLeadIds'])
        ;
        return $events;
    }


    public function findFirstByLeadId(int $leadId): ?CalendlyScheduledEvent
    {
        return CalendlyScheduledEvent::where('clientyLeadIds', $leadId)->orderBy('created_at', 'asc')->first();
    }


    public function findLastByLeadId(int $leadId): ?CalendlyScheduledEvent
    {
        return CalendlyScheduledEvent::where('clientyLeadIds', $leadId)->orderBy('created_at', 'desc')->first();
    }


    protected function validateStoreData(CalendlyScheduledEvent $scheduledEvent): void
    {
        if (!$scheduledEvent->invitees) {
            throw new Exception('scheduled_event_invitees_are_empty');
        }
        if (!($scheduledEvent->invitees[0]['email'] ?? null)) {
            throw new Exception('scheduled_event_invitee_email_is_empty');
        }
        if (!$scheduledEvent->hash) {
            throw new Exception('scheduled_event_hash_is_empty');
        }
        if (!$scheduledEvent->uri) {
            throw new Exception('scheduled_event_uri_is_empty');
        }
    }

}
