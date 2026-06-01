<?php

namespace App\Services\API\Views\Reports;

use DateTime;
use App\Models\Status;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\API\Views\LeadService;
use App\Services\Traits\GetClientFromRequest;


class UserCloseRateReportService
{
    
    use GetClientFromRequest;


    public function __construct()
    {
    }


    public function list(array $options): array
    {
        $filters = $options['filters'] ?? [];
        $breakdown = $options['breakdown'] ?? 'monthly';
        $closeDateType = $options['close_date_type'] ?? 'leads';

        if ($breakdown == 'weekly') {
            $dateNow = new DateTime('now');
            $startDate = new DateTime('-5 weeks monday');
            $endDate = ($dateNow->format('l') == 'Sunday') ? $dateNow : new DateTime('next sunday');

            $addRangeEnd = 'next sunday';
            $addRangeStart = 'next monday';
            $reportInfoByPeriods = $this->getReportInfoByPeriods(
                $closeDateType, $startDate, $endDate, $addRangeStart, $addRangeEnd, $filters
            );
            $report = $this->buildReport($reportInfoByPeriods, $breakdown);
        }

        if ($breakdown == 'monthly') {
            $endDate = new DateTime('last day of this month');
            $startDate = new DateTime('first day of 5 months ago');

            $addRangeEnd = 'last day of this month';
            $addRangeStart = 'first day of next month';
            $reportInfoByPeriods =  $this->getReportInfoByPeriods(
                $closeDateType, $startDate, $endDate, $addRangeStart, $addRangeEnd, $filters
            );
            $report = $this->buildReport($reportInfoByPeriods, $breakdown);
        }

        if ($breakdown == 'quarterly') {
            $endDate = $this->getEndQuarterDate();
            $startDate = $this->getStartQuarterDate();

            $addRangeEnd = 'last day of +2 months';
            $addRangeStart = 'first day of +3 months';
            $reportInfoByPeriods =  $this->getReportInfoByPeriods(
                $closeDateType, $startDate, $endDate, $addRangeStart, $addRangeEnd, $filters
            );
            $report = $this->buildReport($reportInfoByPeriods, $breakdown);
        }

        if ($breakdown == 'yearly') {
            $endDate = new DateTime('12/31 this year');
            $startDate = new DateTime('4 years ago first day of january');

            $addRangeEnd = '12/31 this year';
            $addRangeStart = 'first day of next year';
            $reportInfoByPeriods =  $this->getReportInfoByPeriods(
                $closeDateType, $startDate, $endDate, $addRangeStart, $addRangeEnd, $filters
            );
            $report = $this->buildReport($reportInfoByPeriods, $breakdown);
        }

        if ($breakdown == 'historical') {
            $endDate = new DateTime('now');
            $startDate = new DateTime('50 years ago first day of january');

            $addRangeEnd = '+100 years';
            $addRangeStart = '+100 years';
            $reportInfoByPeriods =  $this->getReportInfoByPeriods(
                $closeDateType, $startDate, $endDate, $addRangeStart, $addRangeEnd, $filters
            );
            $report = $this->buildReport($reportInfoByPeriods, $breakdown);
        }

        return $report;
    }


    private function buildReport(array $reportInfoByPeriods, string $breakdown): array
    {
        $reportPeriods = [];
        foreach ($reportInfoByPeriods as $i => $periodInfo) {
            $periodReport = [];
            foreach ($periodInfo['results'] as $result) {
                $userId = $result->user_id;

                $periodReport[$userId] = [
                    'user_id' => (int) $result->user_id,
                    'leads_count' => (int) $result->leads_count,
                    'total_sales_count' => (int) $result->total_sales_count,
                    'unique_sales_count' => (int) $result->unique_sales_count,
                    // 'last_3_months_total_sales_count' => (int) $result->last_3_months_total_sales_count,
                ];
            }
            $reportPeriods[] = [
                'users' => $periodReport,
                'period_dates' => $periodInfo['period_dates'],
            ];
        }
        return ['breakdown' => $breakdown, 'report' => array_reverse($reportPeriods)];
    }


    private function getReportInfoByPeriods(
        string $closeDateType,
        DateTime $startDate,
        DateTime $endDate,
        string $startDateAddRange,
        string $endDateAddRange,
        array $filters
    ): array {
        $startRangeDate = (clone $startDate)->setTime(0, 0, 0);
        $endRangeDate = (clone $startDate)->modify($endDateAddRange)->setTime(23, 59, 59);

        $periods = [];
        while ($endDate >= $startRangeDate) {
            $results = $this->findUserCloseRateReportData($closeDateType, $startRangeDate, $endRangeDate, $filters);
            $periods[] = [
                'results' => $results,
                'period_dates' => [
                    'date_end' => $endRangeDate->format('Y-m-d'),
                    'date_start' => $startRangeDate->format('Y-m-d'),
                ],
            ];
            $startRangeDate = (clone $startRangeDate)->modify($startDateAddRange)->setTime(0, 0, 0);
            $endRangeDate = (clone $startRangeDate)->modify($endDateAddRange)->setTime(23, 59, 59);
        }
        return $periods;
    }


    public function findUserCloseRateReportData(
        string $closeDateType,
        Datetime $dateStart,
        Datetime $dateEnd,
        array $filters = []
    ): Collection {
        $client = $this->getClient();
        $userIds = $filters['user_id'] ?? [];
        $statusIds = $filters['status_id'] ?? [];
        $dateEndStr = $dateEnd->format('Y-m-d');
        $dateStartStr = $dateStart->format('Y-m-d');
        $isLeadsCloseDateType = $closeDateType == 'leads';
        $isSalesCloseDateType = $closeDateType != 'leads';

        $usersQuery = DB::table('Users')->where('client_id', $client->id)->whereNull('deleted_at');
        if ($userIds) {
            $usersQuery->whereIn('id', $userIds);
        }
        $users = $usersQuery->get(['id']);

        $statusQuery = DB::table('Status')->where('Status.client_id', $client->id)->whereNull('Status.deleted_at')
            ->join('StatusCategories', 'StatusCategories.id', '=', 'Status.status_category_id')
            ->where('StatusCategories.is_irrelevant', false)
        ;
        if ($statusIds) {
            $statusQuery->whereIn('Status.id', $statusIds);
        }
        $statusList = $statusQuery->get(['Status.id']);

        $leadsQuery = DB::table('Leads')->where('client_id', $client->id)->whereNull('deleted_at');
        $leadsQuery->whereIn('user_id', $users->pluck('id'));
        $leadsQuery->whereIn('status_id', $statusList->pluck('id'));
        $leadsQuery->whereRaw("DATE(lead_created_at) <= '{$dateEndStr}'");
        $leadsQuery->whereRaw("DATE(lead_created_at) >= '{$dateStartStr}'");
        $leads = $leadsQuery->get(['id', 'user_id']);

        $leadSalesQuery = DB::table('LeadsSales')->where('client_id', $client->id)->whereNull('deleted_at');
        if ($isLeadsCloseDateType) {
            $leadSalesQuery->whereIn('lead_id', $leads->pluck('id'));
        }
        if ($isSalesCloseDateType) {
            $leadSalesQuery->whereRaw("DATE(sale_date) <= '{$dateEndStr}'");
            $leadSalesQuery->whereRaw("DATE(sale_date) >= '{$dateStartStr}'");
        }
        $leadSales = $leadSalesQuery->get(['id', 'user_id', 'lead_id']);

        $report = new Collection();
        foreach ($users as $user) {
            $reportRow = ['user_id' => $user->id];
            $reportRow['leads_count'] = $leads->where('user_id', $user->id)->count();
            
            $userSales = $leadSales->where('user_id', $user->id);
            if ($isLeadsCloseDateType) {
                $reportRow['total_sales_count'] = $userSales->count();
                $reportRow['unique_sales_count'] = $userSales->pluck('lead_id')->unique()->count();
            }
            if ($isSalesCloseDateType) {
                $reportRow['total_sales_count'] = $userSales->count();
                $reportRow['unique_sales_count'] = $userSales->count();
            }
            $report->push((object) $reportRow);
        }
        return $report;
    }


    public function getStartQuarterDate()
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


    public function getEndQuarterDate()
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

}
