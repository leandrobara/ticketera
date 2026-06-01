<?php

namespace App\Services\API\Views\Reports;

use DateTime;
use DateTimeZone;
use App\Models\Status;
use App\Services\API\Views\LeadService;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\ProposalInfoRepository;
use App\Services\Traits\GetClientFromRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Filter\LeadSales\TagORCriteria;
use App\Repositories\Criteria\Filter\ProposalInfo\LandingCriteria;
use App\Repositories\Criteria\Filter\ProposalInfo\LimitSentEndCriteria;
use App\Repositories\Criteria\Filter\ProposalInfo\LimitSentStartCriteria;
use App\Repositories\Criteria\Filter\ProposalInfo\AcquisitionChannelCriteria;


class SentProposalReportService
{

    use GetClientFromRequest;

    private $leadService;
    private $proposalInfoRepository;


    public function __construct(ProposalInfoRepository $proposalInfoRepository, LeadService $leadService)
    {
        $this->leadService = $leadService;
        $this->proposalInfoRepository = $proposalInfoRepository;
    }


    public function list(array $options): LengthAwarePaginator
    {
        $client = $this->getClient();
        $search = $options['filters']['search'] ?? null;

        $opts = [
            'order' => 'sent_date DESC',
            'page' => $options['page'] ?? 1,
            'limit' => $options['limit'] ?? 20,
            'filters' => $this->getFilterCriteriasByName($options['filters'] ?? []),
        ];
        $opts = $this->addLeadStatusDiscardedConditionToOptions($opts);

        if (!$search) {
            $response = $this->proposalInfoRepository->listPaginated($client, $opts);
        } else {
            $leadIds = $this->leadService->listIds(['filters' => ['search' => $search]]);
            unset($opts['filters']['search']);
            $response = $this
                ->proposalInfoRepository
                ->listPaginatedByCliendAndLeadIds($client, $leadIds, $opts)
            ;
        }
        return $response;
    }


    public function export(array $options)
    {
        $options['limit'] = 9999999999;
        return $this->list($options)->getCollection();
    }


    public function summary(array $options): array
    {
        $breakdown = $options['breakdown'] ?? null;
        $result = $this->getPeriodsByBreakDown($breakdown, $options);
        return $result;
    }


    private function getFilterCriteriasByName(array $filters): array
    {
        $criterias = [
            'tag_id' => TagORCriteria::class,
            'landing_id' => LandingCriteria::class,
            'send_date_end' => LimitSentEndCriteria::class,
            'send_date_start' => LimitSentStartCriteria::class,
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


    private function getPeriodsByBreakDown(string $breakdown, array $options): array
    {
        $dateNow = new DateTime('now');
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
            $startMonthDate = new DateTime('first day of 5 months ago');
            $endMonthDate = new DateTime('last day of this month');

            $addRangeEnd = 'last day of this month';
            $addRangeStart = 'first day of next month';
            $periods =  $this->createBreakDownPeriods(
                $startMonthDate, $endMonthDate, $addRangeStart, $addRangeEnd, $options
            );
            $report = $this->createReport($periods, $breakdown);
        }

        if ($breakdown == 'quarterly') {
            $startQuarterDate = $this->getStartQuarterDate();
            $endQuarterDate = $this->getEndQuarterDate();

            $addRangeEnd = 'last day of +2 months';
            $addRangeStart = 'first day of +3 months';
            $periods =  $this->createBreakDownPeriods(
                $startQuarterDate, $endQuarterDate, $addRangeStart, $addRangeEnd, $options
            );
            $report = $this->createReport($periods, $breakdown);
        }

        if ($breakdown == 'yearly') {
            $startYearDate = new DateTime('4 years ago first day of january');
            $endYearDate = new DateTime('12/31 this year');

            $addRangeEnd = '12/31 this year';
            $addRangeStart = 'first day of next year';
            $periods =  $this->createBreakDownPeriods(
                $startYearDate, $endYearDate, $addRangeStart, $addRangeEnd, $options
            );
            $report = $this->createReport($periods, $breakdown);
        }

        if ($breakdown == 'historical') {
            $period = $this->createHistoricalBreakDown();
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
        $options['filters'] = $this->getFilterCriteriasByName($options['filters'] ?? []);
        $options = $this->addLeadStatusDiscardedConditionToOptions($options);

        $filterStartDate = (clone $startDate)->setTimezone($clientTz)->setTime(0, 0, 0)->setTimezone($utcTz);
        $filterEndDate = (clone $endDate)->setTimezone($clientTz)->setTime(23, 59, 59)->setTimezone($utcTz);

        if (!$search) {
            $proposals = $this
                ->proposalInfoRepository
                ->findProposalInfoByPeriod($this->getClient(), $filterStartDate, $filterEndDate, $options)
            ;
        } else {
            $leadIds = $this->leadService->listIds(['filters' => ['search' => $search]]);
            unset($options['filters']['search']);
            $proposals = $this->proposalInfoRepository->listByClientAndLeadIds(
                $this->getClient(), $leadIds, $options
            );
        }

        $startRangeDate = (clone $startDate)->setTime(0, 0, 0);
        $endRangeDate = (clone $startDate)->modify($endDateAddRange)->setTime(23, 59, 59);
      
        $periods = [];
        while ($endDate >= $startRangeDate) {
            $endRangeDateStr = $endRangeDate->format('Y-m-d H:i:s');
            $startRangeDateStr = $startRangeDate->format('Y-m-d H:i:s');
            $filterEndDate = (new Datetime($endRangeDateStr, $clientTz))->setTimezone($utcTz);
            $filterStartDate = (new Datetime($startRangeDateStr, $clientTz))->setTimezone($utcTz);

            $filteredProposals = $proposals->filter(function ($proposal) use ($filterStartDate, $filterEndDate) {
                if ($proposal->sent_date >= $filterStartDate && $proposal->sent_date <= $filterEndDate) {
                    return $proposal;
                }
            });
            $periods[] = [
                'period_dates' => [
                    'date_end' => $endRangeDate->format('Y-m-d'),
                    'date_start' => $startRangeDate->format('Y-m-d'),
                ],
                'proposals' => $filteredProposals,
            ];
            $startRangeDate = (clone $startRangeDate)->modify($startDateAddRange)->setTime(0, 0, 0);
            $endRangeDate = (clone $startRangeDate)->modify($endDateAddRange)->setTime(23, 59, 59);
        }
        return $periods;
    }


    private function createHistoricalBreakDown()
    {
        $opts = $this->addLeadStatusDiscardedConditionToOptions([]);
        $sentProposals = $this->proposalInfoRepository->findAllByClient($this->getClient(), $opts);
        $lastProposal = $sentProposals->last();
        $firstProposal = $sentProposals->first();
        $results[] = [
            'proposals' => $sentProposals,
            'period_dates' => [
                'date_end' => $firstProposal ? $firstProposal->sent_date->format('Y-m-d') : null,
                'date_start' => $lastProposal ? $lastProposal->sent_date->format('Y-m-d') : null,
            ]
        ];
        return $results;
    }


    private function createReport(array $periods, $breakdown): array
    {
        $report = [];
        $reportPeriods = [];
        foreach ($periods as $period) {
            $proposalTotalCount = 0;
            $proposalTotalAmount = 0;
            $proposalAvgAmount = 0;
            foreach ($period['proposals'] as $proposal) {
                // if ($proposal->amount && $proposal->amount > 0) {
                    $proposalTotalCount++;
                // }
                $proposalTotalAmount += $proposal->amount;
            }
            if ($proposalTotalAmount && $proposalTotalCount) {
                $proposalAvgAmount = ceil($proposalTotalAmount / $proposalTotalCount);
            }
            $proposalTotalAmount = ceil($proposalTotalAmount);
            $reportPeriods[] = [
                'period_dates' => $period['period_dates'],
                'sent_proposal_count' =>  $proposalTotalCount,
                'sent_proposal_avg_amount' => $proposalAvgAmount,
                'sent_proposal_total_amount' => $proposalTotalAmount,
            ];
        }
        $reportPeriods =  array_reverse($reportPeriods);
        $report['breakdown'] = $breakdown;
        $report['summary'] = $reportPeriods;
        return $report;
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


    private function addLeadStatusDiscardedConditionToOptions(array $opts): array
    {
        $client = $this->getClient();
        // $conditionArr = [
        //     'lead.status.statusCategory' => function (Builder $query) use ($client) {
        //         $query->where('is_irrelevant', false)->where('client_id', $client->id);
        //     }
        // ];
        $conditionArr = [
            'lead' => function ($q1) use ($client) {
                $q1->where('client_id', $client->id)->whereHas('status', function ($q2) use ($client) {
                    $q2->where('client_id', $client->id)->whereHas('statusCategory', function ($q3) use ($client) {
                        $q3->where('client_id', $client->id)->where('is_irrelevant', false);
                    });
                });
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
