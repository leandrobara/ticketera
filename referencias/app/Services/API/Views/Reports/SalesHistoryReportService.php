<?php

namespace App\Services\API\Views\Reports;

use DateTime;
use DateTimeZone;
use App\Models\Status;
use App\Models\LeadSale;
use Illuminate\Support\Collection;
use App\Services\API\Views\LeadService;
use App\Repositories\LeadSaleRepository;
use Illuminate\Database\Eloquent\Builder;
use App\Services\API\ProposalInfoService;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Cache\LeadSaleRepositoryCache;
use App\Repositories\Criteria\Filter\LeadSales\TagORCriteria;
use App\Repositories\Criteria\Filter\LeadSales\LandingCriteria;
use App\Repositories\Criteria\Filter\LeadSales\SalesTypeCriteria;
use App\Repositories\Criteria\Filter\LeadSales\SaleDateEndCriteria;
use App\Repositories\Criteria\Filter\LeadSales\SaleDateStartCriteria;
use App\Repositories\Criteria\Filter\LeadSales\AcquisitionChannelCriteria;


class SalesHistoryReportService
{

    use GetClientFromRequest;

    private $leadService;
    private $leadSaleRepository;


    public function __construct(
        LeadSaleRepository | LeadSaleRepositoryCache $leadSaleRepository,
        LeadService $leadService
    ) {
        $this->leadService = $leadService;
        $this->leadSaleRepository = $leadSaleRepository;
    }


    public function list(array $options): LengthAwarePaginator
    {
        $client = $this->getClient();
        $search = $options['filters']['search'] ?? null;

        $opts = [
            'with' => [
                'lead',
                'user',
                'lead.tags',
                'lead.user',
                'lead.status',
                'lead.landing',
                'lead.leadSales',
                'lead.leadContacts',
                'lead.mainLeadContact',
                'lead.lastProposalInfo',
                'lead.acquisitionChannel',
                'lead.leadContacts.leadContactEmails',
                'lead.leadContacts.leadContactPhones',
                'lead.mainLeadContact.leadContactPhones',
                'lead.mainLeadContact.leadContactEmails',
            ],
            'page' => $options['page'] ?? 1,
            'limit' => $options['limit'] ?? 20,
            'filters' => $this->getFilterCriteriasByName($options['filters'] ?? []),
        ];

        $opts = $this->addLeadStatusDiscardedConditionToOptions($opts);
        if (!$search) {
            $response = $this->leadSaleRepository->listPaginated($this->getClient(), $opts);
        } else {
            $leadIds = $this->leadService->listIds(['filters' => ['search' => $search]]);
            unset($opts['filters']['search']);
            $response = $this->leadSaleRepository->listPaginatedByCliendAndLeadIds($client, $leadIds, $opts);
        }
        $response = $this->addSaleCloseTime($response);
        return $response;
    }


    public function export(array $options): Collection
    {
        $options['limit'] = 9999999999999999;
        return $this->list($options)->getCollection();
    }


    public function summary(array $options): array
    {
        $dateNow = new DateTime('now');
        $breakdown = $options['breakdown'] ?? null;

        if ($breakdown == 'weekly') {
            $startWeekDate = new DateTime('-5 weeks monday');
            $endWeekDate = ($dateNow->format('l') == 'Sunday') ? $dateNow : new DateTime('next sunday');

            $addRangeEnd = 'next sunday';
            $addRangeStart = 'next monday';
            $periods = $this->createBreakDownPeriods(
                $startWeekDate, $endWeekDate, $addRangeStart, $addRangeEnd, $options
            );
            $report = $this->createReport($periods, $breakdown);
        }

        if ($breakdown == 'monthly') {
            $endMonthDate = new DateTime('last day of this month');
            $startMonthDate = new DateTime('first day of 5 months ago');

            $addRangeEnd = 'last day of this month';
            $addRangeStart = 'first day of next month';
            $periods =  $this->createBreakDownPeriods(
                $startMonthDate, $endMonthDate, $addRangeStart, $addRangeEnd, $options
            );
            $report = $this->createReport($periods, $breakdown);
        }

        if ($breakdown == 'quarterly') {
            $endQuarterDate = $this->getEndQuarterDate();
            $startQuarterDate = $this->getStartQuarterDate();

            $addRangeEnd = 'last day of +2 months';
            $addRangeStart = 'first day of +3 months';
            $periods =  $this->createBreakDownPeriods(
                $startQuarterDate, $endQuarterDate, $addRangeStart, $addRangeEnd, $options
            );
            $report = $this->createReport($periods, $breakdown);
        }

        if ($breakdown == 'yearly') {
            $endYearDate = new DateTime('12/31 this year');
            $startYearDate = new DateTime('4 years ago first day of january');

            $addRangeEnd = '12/31 this year';
            $addRangeStart = '01/01 next year';
            $periods =  $this->createBreakDownPeriods(
                $startYearDate, $endYearDate, $addRangeStart, $addRangeEnd, $options
            );
            $report = $this->createReport($periods, $breakdown);
        }

        if ($breakdown == 'historical') {
            $period = $this->createHistoricalBreakDown($options);
            $report = $this->createReport($period, $breakdown);
        }

        return $report;
    }


    private function createBreakDownPeriods(
        Datetime $startDate,
        DateTime $endDate,
        string $startDateAddRange,
        string $endDateAddRange,
        array $options
    ): array {
        $client = $this->getClient();
        $utcTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($client->timezone);

        $search = $options['filters']['search'] ?? null;
        $options = $this->addLeadStatusDiscardedConditionToOptions($options);
        $options['filters'] = $this->getFilterCriteriasByName($options['filters'] ?? []);

        $filterStartDate = (clone $startDate)->setTimezone($clientTz)->setTime(0, 0, 0)->setTimezone($utcTz);
        $filterEndDate = (clone $endDate)->setTimezone($clientTz)->setTime(23, 59, 59)->setTimezone($utcTz);
        
        if (!$search) {
            $sales = $this->leadSaleRepository->findByClientAndDates(
                $client, $filterStartDate, $filterEndDate, $options
            );
        } else {
            $leadIds = $this->leadService->listIds(['filters' => ['search' => $search]]);
            unset($options['filters']['search']);
            $sales = $this->leadSaleRepository->listByClientAndLeadIds($client, $leadIds, $options);
        }

        $startRangeDate = (clone $startDate)->setTime(0, 0, 0);
        $endRangeDate = (clone $startDate)->modify($endDateAddRange)->setTime(23, 59, 59);

        $periods = [];
        while ($endDate >= $startRangeDate) {
            $endRangeDateStr = $endRangeDate->format('Y-m-d H:i:s');
            $startRangeDateStr = $startRangeDate->format('Y-m-d H:i:s');
            $filterEndDate = (new Datetime($endRangeDateStr, $clientTz))->setTimezone($utcTz);
            $filterStartDate = (new Datetime($startRangeDateStr, $clientTz))->setTimezone($utcTz);

            $filteredSales = $sales->filter(function ($sale) use ($filterStartDate, $filterEndDate) {
                if ($sale->sale_date >= $filterStartDate && $sale->sale_date <= $filterEndDate) {
                    return $sale;
                }
            });
            $periods[] = [
                'period_dates' => [
                    'date_end' => $endRangeDate->format('Y-m-d'),
                    'date_start' => $startRangeDate->format('Y-m-d'),
                ],
                'sales' => $filteredSales,
            ];
            $startRangeDate = (clone $startRangeDate)->modify($startDateAddRange)->setTime(0, 0, 0);
            $endRangeDate = (clone $startRangeDate)->modify($endDateAddRange)->setTime(23, 59, 59);
        }
        return $periods;
    }


    private function createReport(array $periods, $breakdown): array
    {
        $report = [];
        $reportPeriods = [];
        foreach ($periods as $period) {
            $salesAvgAmount = 0;
            $salesTotalCount = 0;
            $salesTotalAmount = 0;
            foreach ($period['sales'] as $sales) {
                // if ($sales->amount && $sales->amount > 0) {
                //     $salesTotalCount++;
                // }
                $salesTotalCount++;
                $salesTotalAmount += $sales->amount;
            }
            if ($salesTotalAmount) {
                $salesAvgAmount = ceil($salesTotalAmount / $salesTotalCount);
            }
            $reportPeriods[] = [
                'period_dates' => $period['period_dates'],
                'sales_count' =>  $salesTotalCount,
                'sales_avg_amount' => $salesAvgAmount,
                'sales_total_amount' => $salesTotalAmount,
            ];
        }

        $reportPeriods =  array_reverse($reportPeriods);
        $report['breakdown'] = $breakdown;
        $report['summary'] = $reportPeriods;
        return $report;
    }


    private function createHistoricalBreakDown()
    {
        $opts = $this->addLeadStatusDiscardedConditionToOptions([]);
        $sales = $this->leadSaleRepository->findAllByClient($this->getClient(), $opts);
        $hasSales = !$sales->isEmpty();
        $results[] = [
            'sales' => $sales,
            'period_dates' => [
                'date_end' => $hasSales ? $sales->first()->sale_date->format('Y-m-d') : null,
                'date_start' => $hasSales ? $sales->last()->sale_date->format('Y-m-d') : null,
            ]
        ];
        return $results;
    }


    private function getStartQuarterDate()
    {
        $dateNow = new Datetime('now');
        $monthNumber = (int) $dateNow->format('m');

        if ($monthNumber >= 10) {
            return new DateTime('first day of january this year');
        }
        if ($monthNumber >= 7) {
            return new DateTime('first day of october last year');
        }
        if ($monthNumber >= 4) {
            return new DateTime('first day of july last year');
        }
        return new DateTime('first day of january last year');
    }


    private function getEndQuarterDate()
    {
        $dateNow = new Datetime('now');
        $monthNumber = (int) $dateNow->format('m');

        if ($monthNumber >= 10) {
            return new DateTime('last day of december this year');
        }
        if ($monthNumber >= 7) {
            return new DateTime('last day of september this year');
        }
        if ($monthNumber >= 4) {
            return new DateTime('last day of june this year');
        }
        return new DateTime('last day of march this year');
    }


    private function addSaleCloseTime(LengthAwarePaginator $paginatedResponse): LengthAwarePaginator
    {
        $leadSales = $paginatedResponse->getCollection();
        foreach ($leadSales as $leadSale) {
            // get all the sales from a lead
            $salesCount = $leadSale->lead->leadSales->count();
            // add its first sale
            $leadSale->isFirstSale = $salesCount > 1 ? false : true;
            // add days count between lead creation and sale
            $leadSale->daysBetweenLeadCreationAndSale = $this->calculateCloseFromCreationTime($leadSale);
            // add days count between last sent proposal and sale
            $leadSale->daysBetweenLastSentProposalAndSale = $this->calculateCloseFromLastSentProposal($leadSale);
        }
        return $paginatedResponse;
    }


    private function calculateCloseFromLastSentProposal(LeadSale $leadSale): ?int
    {
        $lastProposal = $leadSale->lead->lastProposalInfo;
        if (!$lastProposal) {
            return null;
        }
        return $leadSale->sale_date->diff($lastProposal->sent_date)->format('%a');
    }


    private function calculateCloseFromCreationTime(LeadSale $leadSale): int
    {
        $saleCreation = $leadSale->sale_date;
        $leadCreation = $leadSale->lead->created_at;
        return $saleCreation->diff($leadCreation)->format('%a');
    }


    private function getFilterCriteriasByName($filters): array
    {
        $criterias = [
            'tag_id' => TagORCriteria::class,
            'landing_id' => LandingCriteria::class,
            'sales_type' => SalesTypeCriteria::class,
            'sale_date_end' => SaleDateEndCriteria::class,
            'sale_date_start' => SaleDateStartCriteria::class,
            'acquisition_channel_id' => AcquisitionChannelCriteria::class,
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


    private function addLeadStatusDiscardedConditionToOptions(array $opts): array
    {
        $conditionArr = [
            'lead.status.statusCategory' => function (Builder $query) {
                $query->where('is_irrelevant', false);
            }
        ];
        if (!isset($opts['whereHas'])) {
            $opts['whereHas'] = $conditionArr;
        } else {
            $opts['whereHas'][] = $conditionArr;
        }
        return $opts;
    }

}
