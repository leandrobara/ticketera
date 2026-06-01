<?php

namespace App\Services\API\Views\Reports;

use App\Repositories\LeadRepository;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;


class LeadReportService
{

    use GetClientFromRequest;

    private $leadRepository;


    public function __construct(LeadRepository $leadRepository)
    {
        $this->leadRepository = $leadRepository;
    }


    public function list(array $options): LengthAwarePaginator
    {
        $client = $this->getClient();
        $search = $options['filters']['search'] ?? null;

        $opts = [
            'page' => $options['page'] ?? 1,
            'limit' => $options['limit'] ?? 20,
            'filters' => $this->getFilterCriteriasByName($options['filters'] ?? []),
        ];
        if (!$search) {
            $response = $this->proposalInfoRepository->listPaginated($client, $opts);
        } else {
            $leadIds = $this->leadService->listIds(['filters' => ['search' => $search]]);
            unset($opts['filters']['search']);
            $response = $this->proposalInfoRepository->listPaginatedByCliendAndLeadIds(
                $client, $leadIds->toArray(), $opts
            );
        }
        return $response;
    }


    private function getFilterCriteriasByName(array $filters): array
    {
        $criterias = [
            'date_start' => LimitSentStartCriteria::class,
            'date_end' => LimitSentEndCriteria::class
        ];
        $nfilters = [];
        foreach ($filters as $key => $value) {
            if ($value) {
                if (in_array($key, array_keys($criterias))) {
                    $nfilters[$key] = new $criterias[$key]($value);
                } else {
                    $nfilters[$key] =  $value;
                }
            }
        }
        return $nfilters;
    }

}
