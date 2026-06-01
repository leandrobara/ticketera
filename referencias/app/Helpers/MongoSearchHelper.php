<?php

namespace App\Helpers;

use DateTime;
use App\Models\Lead;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\Manager;
use Illuminate\Support\Str;
use MongoDB\BSON\UTCDateTime;
use App\Helpers\StringHelper;
use MongoDB\Driver\BulkWrite;
use MongoDB\Model\BSONDocument;
use MongoDB\Client as MongoClient;
use Illuminate\Support\Collection;
use App\Exceptions\Helpers\MongoSearchHelper\MongoQueryException;


class MongoSearchHelper
{

    private $user;
    private $pass;
    private $dbName;
    private $isAtlas;
    private $mongoClient;
    private $collectionName;


    public function __construct(
        string $dbHost,
        string $dbName,
        string $user,
        string $pass,
        string $collectionName,
        bool $isAtlas
    ) {
        $this->user = $user;
        $this->pass = $pass;
        $this->dbHost = $dbHost;
        $this->dbName = $dbName;
        $this->isAtlas = $isAtlas;
        $this->collectionName = $collectionName;
    }


    public function search(string $searchTerm, array $opts = []): Collection
    {
        $searchTerm = resolve(StringHelper::class)->removeEmojis($searchTerm);
        if (!$searchTerm) {
            throw new MongoQueryException('searchTerm is empty');
        }

        $offset = $opts['offset'] ?? 0;
        $limit = $opts['limit'] ?? 999999;
        $clientId = $opts['filters']['client_id'] ?? null;
        $isDoubleTermSearch = $this->isDoubleTermSearch($searchTerm);
        $isEmail = filter_var($searchTerm, FILTER_VALIDATE_EMAIL) ? true : false;
        if (!$clientId) {
            throw new MongoQueryException('client_id is empty');
        }

        $collectionOpts = [
            'skip' => (int) $offset,
            'limit' => (int) $limit,
            'sort' => ['score' => ['$meta' => 'textScore']],
            'projection' => ['score' => ['$meta' => 'textScore']],
            'typeMap' => ['root' => 'array', 'document' => 'array'],
        ];
        if ($opts['fields'] ?? null) {
            $projection = collect($opts['fields'])->mapWithKeys(function ($fieldName) {
                return [$fieldName => 1];
            })->toArray();
            $collectionOpts['projection'] = array_merge($collectionOpts['projection'], $projection);
        }

        $escapedSearchTerm = "\"$searchTerm\"";
        $filters = ['client_id' => (int) $clientId, '$text' => ['$search' => $escapedSearchTerm]];
        if ($isEmail) {
            $filters = ['emails' => ['$eq' => $searchTerm], 'client_id' => (int) $clientId];
            unset($collectionOpts['sort']);
            unset($collectionOpts['projection']['score']);
        }

        $cursor = $this->getMongoCollection()->find($filters, $collectionOpts);
        $docs = iterator_to_array($cursor);
        $docs = collect($docs);
        $docs = $this->sortDocsBySearchTerm($docs, $searchTerm);
        return $docs;
    }


    public function addOrReplaceLead(Lead $lead): string
    {
        $leadId = (string) $lead->id;
        $doc = $this->findDocumentByLeadId($leadId, ['fields' => ['id']]);
        if (!$doc) {
            $insertedId = $this->addLead($lead);
            return $insertedId;
        }

        $isUpdated = $this->updateLead($lead);
        return (string) $doc['_id'];
    }


    public function findDocumentByLeadId(int $leadId, array $opts = []): ?array
    {
        $docs = $this->findDocumentsByLeadIds([$leadId], $opts);
        return $docs->first();
    }


    public function findDocumentsByLeadIds(array $leadIds, array $opts = []): Collection
    {
        $leadIds = collect($leadIds)->map(function ($leadId) {
            return "$leadId";
        })->values()->toArray();

        $collectionOpts = ['typeMap' => ['root' => 'array', 'document' => 'array']];
        if ($opts['fields'] ?? null) {
            $projection = collect($opts['fields'])->mapWithKeys(function ($fieldName) {
                return [$fieldName => 1];
            })->toArray();
            $collectionOpts['projection'] = $projection;
        }

        $filters = ['id' => ['$in' => $leadIds]];
        $cursor = $this->getMongoCollection()->find($filters, $collectionOpts);
        $docs = iterator_to_array($cursor);
        return collect($docs);
    }


    protected function getMongoCollection()
    {
        $mongoClient = $this->getMongoClient();
        return $mongoClient->{$this->dbName}->{$this->collectionName};
    }


    protected function addLead(Lead $lead): string
    {
        $date = new DateTime();
        $leadArr = $lead->toSearchableArray();
        $leadArr['createdAtTs'] = $date->getTimestamp();
        $leadArr['updatedAtTs'] = $date->getTimestamp();
        $leadArr['createdAt'] = new UTCDateTime($date->getTimestamp() * 1000);
        $leadArr['updatedAt'] = new UTCDateTime($date->getTimestamp() * 1000);
        $result = $this->getMongoCollection()->insertOne($leadArr);
        return (string) $result->getInsertedId();
    }


    protected function updateLead(Lead $lead): bool
    {
        $date = new DateTime();
        $leadArr = $lead->toSearchableArray();
        $leadArr['updatedAtTs'] = $date->getTimestamp();
        $leadArr['updatedAt'] = new UTCDateTime($date->getTimestamp() * 1000);

        $result = $this->getMongoCollection()->updateOne(['id' => (string) $lead->id], ['$set' => $leadArr]);
        return $result->getMatchedCount() ? true : false;
    }


    protected function getMongoClient(): MongoClient
    {
        if ($this->mongoClient) {
            return $this->mongoClient;
        }
        
        $hostUri = "{$this->user}:{$this->pass}@{$this->dbHost}";
        $conn = "mongodb://$hostUri";
        if ($this->isAtlas) {
            $conn = "mongodb+srv://{$hostUri}/{$this->dbName}?retryWrites=true&w=majority";
        }
        $this->mongoClient = new MongoClient($conn);
        return $this->mongoClient;
    }



    protected function sortDocsBySearchTerm(Collection $docs, string $searchTerm): Collection
    {
        $sortedDocs = collect([]);
        $nonSortedDocs = collect([]);
        $searchTerm = trim(strtolower($searchTerm));
        $isDoubleTermSearch = $this->isDoubleTermSearch($searchTerm);
    
        foreach ($docs as $doc) {
            $nameMatches = false;
            $fullNames = $doc['full_names'] ?? [];
            foreach ($fullNames as $fullName) {
                $fullNameArr = explode(' ', $fullName);
                if (count($fullNameArr) < 2) {
                    continue;
                }
                $fullNameArr = array_map('strtolower', $fullNameArr);
                if ($isDoubleTermSearch && count($fullNameArr) > 1 && Str::containsAll($searchTerm, $fullNameArr)) {
                    $nameMatches = true;
                    break;
                }
            }
            if ($nameMatches) {
                $sortedDocs->push($doc);
            } else {
                $nonSortedDocs->push($doc);
            }
        }
        $leads = $sortedDocs->merge($nonSortedDocs);
        return $leads;
    }


    protected function isDoubleTermSearch(string $searchTerm): bool
    {
        $arr = explode(' ', $searchTerm);
        return count($arr) > 1;
    }

}
