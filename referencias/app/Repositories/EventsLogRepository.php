<?php

namespace App\Repositories;

use DateTime;
use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use App\Models\LeadSale;
use App\Models\MongoDB\EventLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Eloquent\Builder;
use App\Models\WhatsAppSendingMessage;
use App\Repositories\Criteria\Sort\MongoSortCriteria;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class EventsLogRepository
{

    public function list(Client $client, array $opts = []): Collection
    {
        $order = $opts['order'] ?? null;
        $limit = $opts['limit'] ?? null;
        $fields = $opts['fields'] ?? [];
        $filters = $opts['filters'] ?? [];

        $queryBuilder = EventLog::where('log.client_id', $client->id)->where('system', 'clienty_crm');
        $queryBuilder = $this->addQueryBuilderFilters($queryBuilder, $filters);
        
        if ($order) {
            if (is_a($order, MongoSortCriteria::class)) {
                $queryBuilder = $order->applySort($queryBuilder);
            } else {
                $queryBuilder->orderByRaw($order);
            }
        }
        if ($limit) {
            $queryBuilder->limit($limit);
        }
        if ($fields) {
            return $queryBuilder->select($fields)->get($fields);
        }

        // DB::connection('mongodb_logs')->enableQueryLog();
        $results = $queryBuilder->get();
        // $mongoQueryLog = DB::connection('mongodb_logs')->getQueryLog();
        // dump('$mongoQueryLog', $mongoQueryLog);
        return $results;
    }


    public function findEventsFromManyLeads(Collection $leadIds, array $events, array $opts = []): Collection
    {
        $order = $opts['order'] ?? null;
        $limit = $opts['limit'] ?? null;
        $leadIds = $leadIds->map(fn ($leadId) => intval($leadId))->toArray();
        $queryBuilder = EventLog::whereIn('log.lead.id', $leadIds)->whereIn('event', $events);
        
        if ($limit) {
            $queryBuilder->limit($limit);
        }
        if ($order) {
            if (is_a($order, MongoSortCriteria::class)) {
                $queryBuilder = $order->applySort($queryBuilder);
            } else {
                $queryBuilder->orderByRaw($order);
            }
        }
        // DB::connection('mongodb_logs')->enableQueryLog();
        $logs = $queryBuilder->where('system', 'clienty_crm')->get()->values();
        // $mongoQueryLog = DB::connection('mongodb_logs')->getQueryLog();
        // dd('$mongoQueryLog', $mongoQueryLog);
        return new Collection($logs->toArray());
    }


    public function findEventsFromOneLead(Lead $lead, array $events, array $opts = []): Collection
    {
        $queryBuilder = EventLog::where('log.lead.id', $lead->id)->whereIn('event', $events);
        if ($opts['limit'] ?? null) {
            $queryBuilder->limit($opts['limit']);
        }
        $logs = $queryBuilder->where('system', 'clienty_crm')->get()->values();
        return new Collection($logs->toArray());
    }


    public function store(array $eventLogData, array $opts = []): EventLog
    {
        $this->validateStoreData($eventLogData);
        $eventLog = new EventLog($eventLogData);
        $eventLog->system = 'clienty_crm';
        $eventLog->hash = EventLog::buildHash($eventLog->system, $eventLog->event, $eventLog->log);

        $eventLogDate = $opts['eventLogDate'] ?? new DateTime('now');
        $eventLog->createdAt = $eventLogDate;
        $eventLog->createdAtTs = $eventLogDate->getTimestamp();
        $eventLog->save();

        return $eventLog->fresh();
    }


    protected function addQueryBuilderFilters(Builder $queryBuilder, array $filters): Builder
    {
        foreach ($filters as $key => $value) {
            if (isset($filters[$key])) {
                if (is_array($value)) {
                    $queryBuilder->whereIn($key, $value);
                } elseif ($filters[$key] instanceof MongoFilterCriteria) {
                    $queryBuilder = $filters[$key]->filterMongoQuery($queryBuilder);
                } else {
                    $queryBuilder->where($key, $value);
                }
            }
        }
        return $queryBuilder;
    }


    public function findWhatsAppSendingMessageSentLogs(WhatsAppSendingMessage $wapMessage): Collection
    {
        $eventLogs = EventLog::where('event', 'whatsapp_sending_message_sent')
            ->where('log.lead.id', $wapMessage->lead_id)
            ->where('log.whatsAppSendingMessage.id', $wapMessage->id)
            ->get()
        ;
        return $eventLogs;
    }


    public function updateWhatsAppSendingMessageSuccess(EventLog $eventLog, bool $success): EventLog
    {
        // Actualizar el campo success en el log
        $logData = $eventLog->log;
        $logData['whatsAppSendingMessage']['success'] = $success;
        
        // Recalcular el hash con los nuevos datos
        $newHash = EventLog::buildHash($eventLog->system, $eventLog->event, $logData);
        
        // Actualizar el documento
        $eventLog->log = $logData;
        $eventLog->hash = $newHash;
        $eventLog->save();
        
        return $eventLog->fresh();
    }


    protected function validateStoreData(array $eventLogData): void
    {
        $logData = $eventLogData['log'] ?? null;
        if (!$logData) {
            throw new Exception('event_log_store_log_data_is_empty');
        }
        if (!is_array($logData)) {
            throw new Exception('event_log_store_log_data_is_not_an_array');
        }
        $event = $eventLogData['event'] ?? null;
        if (!$event) {
            throw new Exception('event_log_store_event_data_is_empty');
        }
        if (!is_string($event)) {
            throw new Exception('event_log_store_event_data_is_not_a_string');
        }
    }

}
