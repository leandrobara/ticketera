<?php

namespace App\Services\API\Views\Reports;

use DateTime;
use App\Services\API\Views\LeadService;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\AcquisitionChannelService;


class AcquisitionChannelReportService
{

    use GetClientFromRequest;

    private $leadService;
    private $acquisitionChannelService;


    public function __construct(AcquisitionChannelService $acquisitionChannelService, LeadService $leadService)
    {
        $this->leadService = $leadService;
        $this->acquisitionChannelService = $acquisitionChannelService;
    }


    public function list(array $options): array
    {
        $filters = $options['filters'] ?? [];
        $type = $options['type'] ?? 'sales_per_channel';
        $breakdown = $options['breakdown'] ?? 'monthly';

        if ($breakdown == 'weekly') {
            $dateNow = new DateTime('now');
            $startDate = new DateTime('-5 weeks monday');
            $endDate = ($dateNow->format('l') == 'Sunday') ? $dateNow : new DateTime('next sunday');

            $addRangeEnd = 'next sunday';
            $addRangeStart = 'next monday';
            $reportInfoByPeriods = $this->getReportInfoByPeriods(
                $startDate, $endDate, $addRangeStart, $addRangeEnd, $type, $filters
            );
            $report = $this->buildReport($type, $reportInfoByPeriods, $breakdown);
        }

        if ($breakdown == 'monthly') {
            $endDate = new DateTime('last day of this month');
            $startDate = new DateTime('first day of 5 months ago');

            $addRangeEnd = 'last day of this month';
            $addRangeStart = 'first day of next month';
            $reportInfoByPeriods =  $this->getReportInfoByPeriods(
                $startDate, $endDate, $addRangeStart, $addRangeEnd, $type, $filters
            );
            $report = $this->buildReport($type, $reportInfoByPeriods, $breakdown);
        }

        if ($breakdown == 'quarterly') {
            $endDate = $this->getEndQuarterDate();
            $startDate = $this->getStartQuarterDate();

            $addRangeEnd = 'last day of +2 months';
            $addRangeStart = 'first day of +3 months';
            $reportInfoByPeriods =  $this->getReportInfoByPeriods(
                $startDate, $endDate, $addRangeStart, $addRangeEnd, $type, $filters
            );
            $report = $this->buildReport($type, $reportInfoByPeriods, $breakdown);
        }

        if ($breakdown == 'yearly') {
            $endDate = new DateTime('12/31 this year');
            $startDate = new DateTime('4 years ago first day of january');

            $addRangeEnd = '12/31 this year';
            $addRangeStart = 'first day of next year';
            $reportInfoByPeriods =  $this->getReportInfoByPeriods(
                $startDate, $endDate, $addRangeStart, $addRangeEnd, $type, $filters
            );
            $report = $this->buildReport($type, $reportInfoByPeriods, $breakdown);
        }

        if ($breakdown == 'historical') {
            $endDate = new DateTime('now');
            $startDate = new DateTime('50 years ago first day of january');

            $addRangeEnd = '+100 years';
            $addRangeStart = '+100 years';
            $reportInfoByPeriods =  $this->getReportInfoByPeriods(
                $startDate, $endDate, $addRangeStart, $addRangeEnd, $type, $filters
            );
            $report = $this->buildReport($type, $reportInfoByPeriods, $breakdown);
        }

        return $report;
    }


    private function buildReport(string $type, array $reportInfoByPeriods, string $breakdown): array
    {
        $report = [];
        if ($type == 'sales_per_channel') {
            $report = $this->buildLeadSalesReport($type, $reportInfoByPeriods, $breakdown);
        }
        if ($type == 'proposals_per_channel') {
            $report = $this->buildLeadProposalsReport($type, $reportInfoByPeriods, $breakdown);
        }
        if ($type == 'quality_leads_per_channel') {
            $report = $this->buildQualityLeadsReport($type, $reportInfoByPeriods, $breakdown);
        }
        return $report;
    }


    private function buildLeadSalesReport(string $type, array $reportInfoByPeriods, string $breakdown): array
    {
        $report = [];
        $reportPeriods = [];
        $basePeriodReport = $this->getEmptyReportIndexedByChannelsId($type);
        foreach ($reportInfoByPeriods as $periodInfo) {
            $periodReport = $basePeriodReport;

            foreach ($periodInfo['results'] as $result) {
                $channelId = $result->acquisition_channel_id;

                if ($periodReport[$channelId] ?? null) {
                    $periodReport[$channelId] = [
                        'leads_count' => $result->leads_count,
                        'total_sales_count' => $result->total_sales_count,
                        'unique_sales_count' => $result->unique_sales_count,
                        'acquisition_channel_name' => $periodReport[$channelId]['acquisition_channel_name'],
                    ];
                    continue;
                }
                $periodReport['null'] = [
                    'acquisition_channel_name' => null,
                    'leads_count' => $result->leads_count,
                    'total_sales_count' => $result->total_sales_count,
                    'unique_sales_count' => $result->unique_sales_count,
                ];
            }
            $reportPeriods[] = [
                'channels' => $periodReport,
                'period_dates' => $periodInfo['period_dates'],
            ];
        }
        $report['type'] = $type;
        $report['breakdown'] = $breakdown;
        $report['report'] = array_reverse($reportPeriods);
        return $report;
    }


    private function buildLeadProposalsReport(string $type, array $reportInfoByPeriods, string $breakdown): array
    {
        $report = [];
        $reportPeriods = [];
        $basePeriodReport = $this->getEmptyReportIndexedByChannelsId($type);
        foreach ($reportInfoByPeriods as $periodInfo) {
            $periodReport = $basePeriodReport;

            foreach ($periodInfo['results'] as $result) {
                $channelId = $result->acquisition_channel_id;

                if ($periodReport[$channelId] ?? null) {
                    $periodReport[$channelId] = [
                        'leads_count' => $result->leads_count,
                        'total_proposals_count' => $result->total_proposals_count,
                        'unique_proposals_count' => $result->unique_proposals_count,
                        'acquisition_channel_name' => $periodReport[$channelId]['acquisition_channel_name'],
                    ];
                    continue;
                }
                $periodReport['null'] = [
                    'acquisition_channel_name' => null,
                    'leads_count' => $result->leads_count,
                    'total_proposals_count' => $result->total_proposals_count,
                    'unique_proposals_count' => $result->unique_proposals_count,
                ];
            }
            $reportPeriods[] = [
                'channels' => $periodReport,
                'period_dates' => $periodInfo['period_dates'],
            ];
        }
        $report['type'] = $type;
        $report['breakdown'] = $breakdown;
        $report['report'] = array_reverse($reportPeriods);
        return $report;
    }


    private function buildQualityLeadsReport(string $type, array $reportInfoByPeriods, string $breakdown): array
    {
        $report = [];
        $reportPeriods = [];
        $basePeriodReport = $this->getEmptyReportIndexedByChannelsId($type);
        foreach ($reportInfoByPeriods as $periodInfo) {
            $periodReport = $basePeriodReport;

            foreach ($periodInfo['results'] as $result) {
                $channelId = $result->acquisition_channel_id;

                if ($periodReport[$channelId] ?? null) {
                    $periodReport[$channelId] = [
                        'leads_count' => $result->leads_count,
                        'quality_leads_count' => $result->quality_leads_count,
                        'acquisition_channel_name' => $periodReport[$channelId]['acquisition_channel_name'],
                    ];
                    continue;
                }
                $periodReport['null'] = [
                    'acquisition_channel_name' => null,
                    'leads_count' => $result->leads_count,
                    'quality_leads_count' => $result->quality_leads_count,
                ];
            }
            $reportPeriods[] = [
                'channels' => $periodReport,
                'period_dates' => $periodInfo['period_dates'],
            ];
        }
        $report['type'] = $type;
        $report['breakdown'] = $breakdown;
        $report['report'] = array_reverse($reportPeriods);
        return $report;
    }


    private function getReportInfoByPeriods(
        Datetime $startDate,
        DateTime $endDate,
        string $startDateAddRange,
        string $endDateAddRange,
        string $type = 'sales_per_channel',
        array $filters = []
    ): array {
        $startRangeDate = (clone $startDate)->setTime(0, 0, 0);
        $endRangeDate = (clone $startDate)->modify($endDateAddRange)->setTime(23, 59, 59);

        $periods = [];
        while ($endDate >= $startRangeDate) {
            if ($type == 'sales_per_channel') {
                $results = $this
                    ->leadService
                    ->findLeadAndSalesGroupedByChannels($startRangeDate, $endRangeDate, $filters)
                ;
            }
            if ($type == 'proposals_per_channel') {
                $results = $this
                    ->leadService
                    ->findLeadAndProposalsGroupedByChannels($startRangeDate, $endRangeDate, $filters)
                ;
            }
            if ($type == 'quality_leads_per_channel') {
                $results = $this
                    ->leadService
                    ->findLeadAndQualityGroupedByChannels($startRangeDate, $endRangeDate, $filters)
                ;
            }

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


    private function getEmptyReportIndexedByChannelsId(string $type): array
    {
        $emptyReport = [];
        if ($type == 'sales_per_channel') {
            $emptyReport = $this->getEmptySalesReportIndexedByChannelsId();
        }
        if ($type == 'proposals_per_channel') {
            $emptyReport = $this->getEmptyProposalsReportIndexedByChannelsId();
        }
        if ($type == 'quality_leads_per_channel') {
            $emptyReport = $this->getEmptyQualityReportIndexedByChannelsId();
        }
        return $emptyReport;
    }


    private function getEmptySalesReportIndexedByChannelsId(): array
    {
        $channels = $this->acquisitionChannelService->findAllByClient($this->getClient());

        $report["null"] = [
            'acquisition_channel_name' => null,
            'leads_count' =>  0,
            'total_sales_count' => 0,
            'unique_sales_count' => 0,
        ];
        foreach ($channels as $channel) {
            $report[$channel->id] = [
                'acquisition_channel_name' => $channel->name,
                'leads_count' =>  0,
                'total_sales_count' => 0,
                'unique_sales_count' => 0,
            ];
        }
        return $report;
    }

    private function getEmptyProposalsReportIndexedByChannelsId(): array
    {
        $channels = $this->acquisitionChannelService->findAllByClient($this->getClient());
        $report["null"] = [
            'acquisition_channel_name' => null,
            'leads_count' =>  0,
            'total_proposals_count' => 0,
            'unique_proposals_count' => 0,
        ];
        foreach ($channels as $channel) {
            $report[$channel->id] = [
                'acquisition_channel_name' => $channel->name,
                'leads_count' =>  0,
                'total_proposals_count' => 0,
                'unique_proposals_count' => 0,
            ];
        }
        return $report;
    }


    private function getEmptyQualityReportIndexedByChannelsId(): array
    {
        $channels = $this->acquisitionChannelService->findAllByClient($this->getClient());
        $report["null"] = [
            'acquisition_channel_name' => null,
            'leads_count' =>  0,
            'quality_leads_count' => 0,
        ];
        foreach ($channels as $channel) {
            $report[$channel->id] = [
                'acquisition_channel_name' => $channel->name,
                'leads_count' =>  0,
                'quality_leads_count' => 0,
            ];
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
