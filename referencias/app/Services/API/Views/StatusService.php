<?php

namespace App\Services\API\Views;

use DateTime;
use Illuminate\Support\Collection;
use App\Services\API\EventsLogService;


class StatusService
{

    public function __construct(EventsLogService $eventsLogService)
    {
        $this->eventsLogService = $eventsLogService;
    }


    public function findLeadsStatusTimes(Collection $leadIds): array
    {
        $result = [];
        $events = $this->eventsLogService->findEventsFromManyLeads(
            $leadIds, ['lead_created', 'lead_manually_created', 'lead_status_updated'], ['order' => 'created_date_asc']
        );

        $groupedEvents = $events->groupBy('log.lead.id');
        foreach ($groupedEvents as $leadId => $leadEvents) {
            $leadEvents = $leadEvents->sortBy('createdAtTs');
            // Recorro los eventos de cada lead, desde el más viejo al más nuevo (el más viejo primero)
            foreach ($leadEvents->values() as $index => $event) {
                $eventTime = (new DateTime())->setTimestamp($event['createdAtTs']);
                
                // (Por default) Si es el estado actual del lead (el más nuevo), comparo contra la fecha actual.
                $compareTime = new DateTime();
                // Si hay un estado más nuevo, comparo con la fecha del evento que le sucede.
                $nextEvent = $leadEvents[$index + 1] ?? null;
                if ($nextEvent) {
                    $compareTime = (new DateTime())->setTimestamp($nextEvent['createdAtTs']);
                }

                $daydiff = $eventTime->diff($compareTime)->format('%a');
                $hourDiff = $eventTime->diff($compareTime)->format('%h');
                $minutesDiff = $eventTime->diff($compareTime)->format('%i');
                
                $result[$event['log']['lead']['id']][] = [
                    'status' => $event['log']['status'],
                    'eventDateTs' => $eventTime->getTimestamp(),
                    'time' => ['days' => $daydiff, 'hours' => $hourDiff, 'minutes' => $minutesDiff]
                ];
            }
        }
        // Entrego ordenado del estado más nuevo al más viejo (el más nuevo primero)
        $invertedResult = collect($result)->map(function ($leadEvents) {
            return array_reverse($leadEvents);
        })->toArray();
        return $invertedResult;
    }

}
