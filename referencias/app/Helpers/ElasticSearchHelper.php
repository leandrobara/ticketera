<?php

namespace App\Helpers;

use App\Models\Lead;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Pagination\LengthAwarePaginator;
use ElasticScoutDriver\Factories\DocumentFactory;
use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use App\Repositories\Criteria\Filter\ElasticFilterCriteria;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use App\Exceptions\Helpers\ElasticSearchHelper\ElasticQueryException;
use App\Exceptions\Helpers\ElasticSearchHelper\AddToElasticIndexException;


// @DEPRECATED NO se usa más elastic.
class ElasticSearchHelper
{

    protected $elasticPass;
    protected $elasticHost;
    protected $elasticPort;
    protected $elasticUser;
    protected $elasticScheme;
    protected $searchTries = 0;


    public function __construct(array $elasticHostInfo)
    {
        $this->elasticPass = $elasticHostInfo[0]['pass'] ?? null;
        $this->elasticPort = $elasticHostInfo[0]['port'] ?? null;
        $this->elasticUser = $elasticHostInfo[0]['user'] ?? null;
        $this->elasticScheme = $elasticHostInfo[0]['scheme'] ?? null;
        $this->elasticHost = $elasticHostInfo[0]['host'] ?? $elasticHostInfo[0];
        $this->documentFactory = resolve(DocumentFactory::class);
    }


    public function findDocumentsByLeadIds(array $leadIds, array $opts = []): Collection
    {
        $fields = $opts['fields'] ?? [];
        $query = [
            'from' => 0,
            '_source' => $fields,
            'size' => count($leadIds),
            'query' => ['ids' => ['values' => $leadIds]]
        ];
        $rawBody = json_encode($query);
        $endpoint = $this->getElasticEndpoint() . '/_search';

        $request = Http::withBody($rawBody, 'application/json')->withOptions(['verify' => false]);
        if ($this->elasticUser && $this->elasticPass) {
            $request->withBasicAuth($this->elasticUser, $this->elasticPass);
        }
        $response = $request->post($endpoint)->body();
        $responseArr = json_decode($response, true);

        $hasErrors = ($responseArr['error'] ?? false) || (!($responseArr['hits'] ?? false));
        if ($hasErrors) {
            $msg = 'Error when querying Elastic. Serialized response: ' . serialize($responseArr);
            throw new ElasticQueryException($msg);
        }
        $docs = collect($responseArr['hits']['hits'])->map(function ($doc) {
            return $doc['_source'];
        });
        return $docs;
    }


    public function addLeadToElasticIndex(Lead $lead): array
    {
        $elasticDocId = (string) $lead->id;
        $bodyPart2 = json_encode($lead->toSearchableArray());
        $bodyPart1 = json_encode(['index' => ['_id' => $elasticDocId]]);
        $rawBody = $bodyPart1 . PHP_EOL . $bodyPart2 . PHP_EOL;
        
        $endpoint = $this->getElasticEndpoint() . '/_bulk?refresh=false';

        $request = Http::withBody($rawBody, 'application/json')->withOptions(['verify' => false]);
        if ($this->elasticUser && $this->elasticPass) {
            $request->withBasicAuth($this->elasticUser, $this->elasticPass);
        }
        $response = $request->post($endpoint)->body();
        $responseArr = json_decode($response, true);

        $hasErrors = $responseArr['errors'] ?? true;
        $operation = $responseArr['items'][0]['index']['result'] ?? null;
        $operationIsCorrect = $operation == 'updated' || $operation == 'created';
        
        if ($hasErrors || !$operationIsCorrect) {
            $msg = 'Error when adding to Elastic. Serialized response: ' . serialize($responseArr);
            throw new AddToElasticIndexException($msg);
        }
        return $responseArr;
    }


    // @TODO refactorizar este asco: usar pattern strategy
    public function searchPaginatedLeads(string $searchTerm, array $options)
    {
        $searchTerm = trim($searchTerm);
        $page = $options['page'] ?? 1;
        $limit = $options['limit'] ?? 20;
        $filters = $options['filters'] ?? [];
        $getOnlyIds = $options['getOnlyIds'] ?? null;
        $forceEmailSearch = $options['forceEmailSearch'] ?? null;

        $elasticQueryBuilder = Lead::multiMatchSearch()
            ->fields(
                ['names^3', 'last_names^2', 'phones', 'normalized_phones', 'emails', 'company', 'lead_custom_fields']
            )
            ->source(['id'])
            ->maxExpansions(1)
            ->fuzzyTranspositions(false)
            ->prefixLength(3)
            ->minimumShouldMatch(1)
            ->query($searchTerm)
        ;

        // set elastic query type
        $elasticQueryBuilder = $this->setSearchType($elasticQueryBuilder, $searchTerm, $options);

        // filter query
        $postFilter = $this->buildPostFilter($filters);
        if ($postFilter) {
            $elasticQueryBuilder->postFilterRaw($postFilter);
        }
        // dd($elasticQueryBuilder->buildSearchRequest());

        // create pagination
        $paginatedResult = $elasticQueryBuilder->paginate($limit, 'page', $page);
        $this->searchTries++;

        // @todo arreglar este asco
        $isEmail = filter_var($searchTerm, FILTER_VALIDATE_EMAIL) ? true : false;
        if ($this->searchTries == 1 && $isEmail && $paginatedResult->getCollection()->isEmpty()) {
            $searchTerm = Str::substr($searchTerm, 0, 18);
            $searchTerm = Str::before($searchTerm, '@');
            $options['forceEmailSearch'] = true;
            return $this->searchPaginatedLeads($searchTerm, $options);
        }
        if ($this->searchTries == 2 && $forceEmailSearch && $paginatedResult->getCollection()->isEmpty()) {
            $searchTerm = Str::substr($searchTerm, 0, 10);
            $searchTerm = Str::before($searchTerm, '@');
            $options['searchType'] = 'bool_prefix';
            $options['forceEmailSearch'] = true;
            return $this->searchPaginatedLeads($searchTerm, $options);
        }

        // get leads from elastic indexes
        $leads = $this->getLeadsFromElasticPaginatedIndexResult($paginatedResult, $getOnlyIds);
        // order by search term if applies
        $leads = $this->sortLeadsBySearchTerm($leads, $searchTerm);
        // set collection again
        $paginatedResult->setCollection($leads);

        $this->searchTries = 0;
        return $paginatedResult;
    }


    public function quickSearchLeads(string $searchTerm, array $options)
    {
        $filters = $options['filters'] ?? [];

        $postFilter = $this->buildPostFilter($filters);
        $elasticQueryBuilder = Lead::multiMatchSearch()
            ->fields(
                ['names^3', 'last_names^2', 'company^1', 'phones', 'normalized_phones', 'emails', 'lead_custom_fields']
            )
            ->source(['id', 'names', 'last_names', 'emails'])
            ->maxExpansions(1)
            ->fuzzyTranspositions(false)
            ->prefixLength(3)
            ->minimumShouldMatch(1)
            ->query($searchTerm)
        ;

        // set elastic query type
        $elasticQueryBuilder = $this->setSearchType($elasticQueryBuilder, $searchTerm);

        // filter query
        $postFilter = $this->buildPostFilter($filters);
        if ($postFilter) {
            $elasticQueryBuilder->postFilterRaw($postFilter);
        }

        $results = $elasticQueryBuilder->execute();
        $documents = $results->matches()->map(function ($match) {
            return $match->document();
        });
        return $documents;
    }


    protected function buildPostFilter(array $filters): array
    {
        $postFilter = [];

        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                // array values makes a conditional filter
                $conditionalFilter = [];
                foreach ($value as $subValue) {
                    $conditionalFilter['bool']['should'][] = ['term' => [$key => $subValue]];
                }
                $postFilter['bool']['filter'][] = $conditionalFilter;
            } elseif ($filters[$key] instanceof ElasticFilterCriteria) {
                // criteria filter
                $postFilter['bool']['filter'][] = $filters[$key]->filterElasticQuery();
            } else {
                // simple term
                $postFilter['bool']['filter'][] = ['term' => [$key => $value ]];
            }
        }
        return $postFilter;
    }


    protected function setSearchType(
        SearchRequestBuilder $elasticQueryBuilder,
        string $searchTerm,
        array $options = []
    ) {
        $forceEmailSearch = $options['forceEmailSearch'] ?? null;
        $isDoubleTermSearch = $this->isDoubleTermSearch($searchTerm);
        $isEmail = filter_var($searchTerm, FILTER_VALIDATE_EMAIL) ? true : false;
        $isInteger = filter_var($searchTerm, FILTER_VALIDATE_INT) ? true : false;

        if ($isEmail || $forceEmailSearch) {
            $searchType = $options['searchType'] ?? 'phrase';
            $elasticQueryBuilder->type($searchType)->fields(['emails']);
        } else if ($isDoubleTermSearch) {
            $elasticQueryBuilder->type('bool_prefix')->fuzziness('AUTO');
        } else if ($isInteger) {
            $elasticQueryBuilder->type('phrase')
                ->fields(['id', 'phones', 'normalized_phones', 'lead_custom_fields'])
                ->fuzziness(false)
            ;
        } else {
            $elasticQueryBuilder->type('best_fields')->fuzziness('AUTO');
        }

        return $elasticQueryBuilder;
    }


    protected function sortLeadsBySearchTerm(Collection $leads, string $searchTerm): Collection
    {
        $sortedLeads = collect([]);
        $nonSortedLeads = collect([]);
        $searchTerm = trim(strtolower($searchTerm));
        $isDoubleTermSearch = $this->isDoubleTermSearch($searchTerm);
        $isEmail = filter_var($searchTerm, FILTER_VALIDATE_EMAIL) ? true : false;
    
        foreach ($leads as $lead) {
            $fullNameArr = explode(' ', $lead->main_full_name);
            $fullNameArr = array_map('strtolower', $fullNameArr);
            if ($isDoubleTermSearch && count($fullNameArr) > 1 && Str::containsAll($searchTerm, $fullNameArr)) {
                $sortedLeads->push($lead);
                continue;
            }

            $emails = $lead->leadContactEmails->pluck('email')->toArray();
            if ($isEmail && $emails && Str::contains($searchTerm, $emails)) {
                $sortedLeads->push($lead);
                continue;
            }

            $nonSortedLeads->push($lead);
        }
        $leads = $sortedLeads->merge($nonSortedLeads);
        return $leads;
    }


    protected function getLeadsFromElasticPaginatedIndexResult(
        LengthAwarePaginator $paginatedResult,
        bool $getOnlyIds
    ): Collection {
        // get lead ids from elastic indexes
        $leadIds = $paginatedResult->getCollection()->map(
            function ($item) {
                return $item->document()->getId();
            }
        );
        $dbQueryBuilder = Lead::whereIn('id', $leadIds);
        // Do not use FIELD() SQL function in test because
        // sqlite doesn't have it
        if ($leadIds->isNotEmpty() && !environmentIsTesting()) {
            $dbQueryBuilder->orderByRaw('FIELD(ID,' . implode(',', $leadIds->toArray()) . ')');
        }
        if ($getOnlyIds) {
            $dbQueryBuilder->select(['id']);
        }
        return $dbQueryBuilder->get();
    }


    protected function isDoubleTermSearch(string $searchTerm): bool
    {
        $arr = explode(' ', $searchTerm);
        return count($arr) > 1;
    }


    protected function getElasticEndpoint(): string
    {
        $elasticIndexName = (new Lead())->searchableAs();

        $endpoint = "{$this->elasticHost}/{$elasticIndexName}";
        if ($this->elasticPass) {
            $endpoint = "{$this->elasticScheme}://{$this->elasticHost}/{$elasticIndexName}";
        }
        return $endpoint;
    }

}

