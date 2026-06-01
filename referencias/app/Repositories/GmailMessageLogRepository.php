<?php

namespace App\Repositories;

use DateTime;
use Exception;
use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use App\Models\LeadSale;
use App\Helpers\StringHelper;
use Illuminate\Support\Collection;
use App\Models\MongoDB\GmailMessageLog;
use MongoDB\Laravel\Eloquent\Builder;
use App\DTO\GoogleAPI\GoogleAPIGmailMessageDTO;
use App\Repositories\Criteria\Sort\MongoSortCriteria;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class GmailMessageLogRepository
{

    public function list(Client $client, array $opts = []): Collection
    {
        $sort = $opts['sort'] ?? null;
        $limit = $opts['limit'] ?? null;
        $fields = $opts['fields'] ?? [];
        $offset = $opts['offset'] ?? null;
        $filters = $opts['filters'] ?? [];
        $excludeFields = $opts['excludeFields'] ?? [];

        // Redundante. Para más seguridad.
        $filters['clientyMetadata.client.id'] = (string) $client->id;

        $queryBuilder = GmailMessageLog::query();
        $queryBuilder = $this->addQueryBuilderFilters($queryBuilder, $filters);
        if ($sort) {
            if (is_a($sort, MongoSortCriteria::class)) {
                $queryBuilder = $sort->applySort($queryBuilder);
            } else {
                $orderBy = key($sort);
                $orientation = reset($sort);
                $queryBuilder->orderBy($orderBy, $orientation);
            }
        }
        if ($limit) {
            $queryBuilder->limit($limit);
        }
        if ($offset) {
            $queryBuilder->skip($offset);
        }
        if ($fields) {
            $queryBuilder->select($fields);
        }
        if ($excludeFields) {
            // [campo1, campo2, ...] --> [campo1 => 0, campo2 =>0, ...]
            $excludeFieldsSelectArr = collect($excludeFields)->flip()->map(fn () => 0)->all();
            $queryBuilder->project($excludeFieldsSelectArr);
        }
        return $queryBuilder->get();
    }


    public function count(array $opts = []): int
    {
        $filters = $opts['filters'] ?? [];
        $queryBuilder = GmailMessageLog::query();
        $queryBuilder = $this->addQueryBuilderFilters($queryBuilder, $filters);
        return $queryBuilder->count();
    }


    public function store(Client $client, GoogleAPIGmailMessageDTO $dto): GmailMessageLog
    {
        $this->validateStoreData($dto);
        $storeDataArr = $dto->toArray();
        $storeDataArr = resolve(StringHelper::class)->convertArrayFieldsToString($storeDataArr);

        $gmailMessageLog = new GmailMessageLog($storeDataArr);
        $gmailMessageLog->hash = GmailMessageLog::buildHash($client, $storeDataArr);
        $gmailMessageLog->createdAt = new DateTime();
        $gmailMessageLog->createdAtTs = $gmailMessageLog->createdAt->getTimestamp();
        $gmailMessageLog->save();
        
        return $gmailMessageLog->fresh();
    }


    private function validateStoreData(GoogleAPIGmailMessageDTO $dto): void
    {
        $storeData = $dto->toArray();
        $params = [
            'body',
            'gmailId',
            'headers',
            'subject',
            'snippet',
            'threadId',
            'sentDate',
            // 'emailNameTo',
            'sentMessageId',
            // 'emailNameFrom',
            'emailAddressTo',
            'clientyMetadata',
            'emailAddressFrom',
            'previousSentMessageId',
            'previousSentMessagesIds',
            'isResponseToClientyUser',
            'isResponseFromClientyUser',
        ];
        foreach ($params as $param) {
            if (!isset($storeData[$param])) {
                throw new Exception('The parameter ' . $param . ' is mandatory', 400);
            }
        }
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

}
