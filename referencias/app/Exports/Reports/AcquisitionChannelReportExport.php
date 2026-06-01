<?php

namespace App\Exports\Reports;

use DateTime;
use Illuminate\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Maatwebsite\Excel\Concerns\FromView;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class AcquisitionChannelReportExport implements WithProperties, FromView, WithStyles
{

    use Exportable;
    private $data;


    public function __construct(array $data)
    {
        $this->data = $data;
    }


    public function properties(): array
    {
        return [
            'creator'        => 'Clienty',
            'lastModifiedBy' => 'Clienty',
            'category'       => 'Reporte',
            'company'        => 'Clienty',
            'title'          => 'Clienty Reporte Canales',
            'description'    => 'Clienty Reporte Canales',
            'subject'        => 'Clienty Reporte Canales',
            'keywords'       => 'clienty,reporte,canales',
        ];
    }


    public function view(): View
    {
        $report = $this->buildReport();
        $reportType = $this->data['type'];
        $reportTotals = $this->buildTotals($report);

        if ($reportType ===  'sales_per_channel') {
            return view('api.exports.reports.acquisition_channels_report.sales_per_channel_report', [
                'report' => $report,
                'reportTotals' => $reportTotals,
                'headers' => $this->buildHeadingsFromBreakdown(),
            ]);
        }
        if ($reportType === 'quality_leads_per_channel') {
            return view('api.exports.reports.acquisition_channels_report.quality_leads_per_channel_report', [
                'report' => $report,
                'reportTotals' => $reportTotals,
                'headers' => $this->buildHeadingsFromBreakdown(),
            ]);
        }
        if ($reportType === 'proposals_per_channel') {
            return view('api.exports.reports.acquisition_channels_report.proposals_per_channel_report', [
                'report' => $report,
                'reportTotals' => $reportTotals,
                'headers' => $this->buildHeadingsFromBreakdown(),
            ]);
        }
    }


    public function styles(Worksheet $sheet)
    {
        $headerStyles = [
            'borders' => [
                'allBorders' => ['color' => ['rgb' => '000000'], 'borderStyle' => Border::BORDER_THIN],
            ]
        ];
        $borderRightStyle = [
            'borders' => [
                'right' => ['color' => ['rgb' => '000000'], 'borderStyle' => Border::BORDER_THIN],
            ]
        ];
        $borderTopBottomStyle = [
            'borders' => [
                'top' => ['color' => ['rgb' => '000000'], 'borderStyle' => Border::BORDER_THIN],
                'bottom' => ['color' => ['rgb' => '000000'], 'borderStyle' => Border::BORDER_THIN],
            ]
        ];
        
        $periodsCount = count($this->data['report']);
        $periodColumnSize = $this->getPeriodColumnSize();
        $lastRowNumber = count($this->data['report'][0]['channels']) + 3;
        
        $sheet->getStyle("A1:A{$lastRowNumber}")->applyFromArray($borderRightStyle);

        $lettersArr = range('A', 'Z');
        for ($i = 0; $i < $periodsCount; $i++) {
            $index = ($periodColumnSize * $i) + $periodColumnSize;
            $colLetter = $lettersArr[$index];
            $sheetIndex = "{$colLetter}1:{$colLetter}{$lastRowNumber}";
            $sheet->getStyle($sheetIndex)->applyFromArray($borderRightStyle);
        }

        $lastColLetter = $colLetter;
        // Add borders to the headers
        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray($headerStyles);
        $sheet->getStyle("A2:{$lastColLetter}2")->applyFromArray($borderTopBottomStyle);
        // Add borders to the totals row
        $sheet->getStyle("A{$lastRowNumber}:{$lastColLetter}{$lastRowNumber}")->applyFromArray($borderTopBottomStyle);
    }


    public function buildReport(): array
    {
        $report = [];
        $channelPeriods = collect($this->data['report'])->pluck('channels');
        foreach ($channelPeriods as $channelPeriod) {
            $channelsInfo = collect($channelPeriod)->reverse();
            foreach ($channelsInfo as $channelInfo) {
                $channelInfo = $this->addRateToChannelInfo($channelInfo);
                $report[$channelInfo['acquisition_channel_name'] ?? 'null'][] = $channelInfo;
            }
        }
        return $report;
    }


    protected function addRateToChannelInfo(array $channelInfo): array
    {
        $reportType = $this->data['type'];
        $leadsCount = $channelInfo['leads_count'];

        if ($reportType === 'sales_per_channel') {
            $rate = intval($channelInfo['unique_sales_count'] * 100 / ($leadsCount ?: 1));
            $channelInfo['sales_rate'] = $rate;
        }
        if ($reportType === 'proposals_per_channel') {
            $rate = intval($channelInfo['unique_proposals_count'] * 100 / ($leadsCount ?: 1));
            $channelInfo['proposals_rate'] = $rate;
        }
        if ($reportType === 'quality_leads_per_channel') {
            $rate = intval($channelInfo['quality_leads_count'] * 100 / ($leadsCount ?: 1));
            $channelInfo['quality_rate'] = $rate;
        }

        return $channelInfo;
    }


    protected function buildTotals(array $excelData): array
    {
        $first = reset($excelData);
        $periodsCount = count($first);

        $totalArr = [];
        for ($periodIndex = 0; $periodIndex < $periodsCount; $periodIndex++) {
            $singlePeriodChannelsInfo = array_map(function ($channelPeriods) use ($periodIndex) {
                return $channelPeriods[$periodIndex];
            }, $excelData);

            $reportType = $this->data['type'];
            
            $singlePeriodChannelsInfo = collect($singlePeriodChannelsInfo);
            $totalLeadsCount = $singlePeriodChannelsInfo->sum('leads_count');
            $totalArr[$periodIndex] = ['leads_count' => $totalLeadsCount];

            if ($reportType === 'sales_per_channel') {
                $totalSalesCount = $singlePeriodChannelsInfo->sum('total_sales_count');
                $totalUniqueSalesCount = $singlePeriodChannelsInfo->sum('unique_sales_count');
                $totalSalesRate = intval($totalUniqueSalesCount * 100 / ($totalLeadsCount ?: 1));
                $totalArr[$periodIndex]['sales_rate'] = $totalSalesRate;
                $totalArr[$periodIndex]['total_sales_count'] = $totalSalesCount;
                $totalArr[$periodIndex]['unique_sales_count'] = $totalUniqueSalesCount;
            }

            if ($reportType === 'proposals_per_channel') {
                $totalProposalsCount = $singlePeriodChannelsInfo->sum('total_proposals_count');
                $totalUniqueProposalsCount = $singlePeriodChannelsInfo->sum('unique_proposals_count');
                $totalProposalsRate = intval($totalUniqueProposalsCount * 100 / ($totalLeadsCount ?: 1));
                $totalArr[$periodIndex]['proposals_rate'] = $totalProposalsRate;
                $totalArr[$periodIndex]['total_proposals_count'] = $totalProposalsCount;
                $totalArr[$periodIndex]['unique_proposals_count'] = $totalUniqueProposalsCount;
            }

            if ($reportType === 'quality_leads_per_channel') {
                $qualityLeadsCount = $singlePeriodChannelsInfo->sum('quality_leads_count');
                $qualityRate = intval($qualityLeadsCount * 100 / ($totalLeadsCount ?: 1));
                $totalArr[$periodIndex]['quality_rate'] = $qualityRate;
                $totalArr[$periodIndex]['quality_leads_count'] = $qualityLeadsCount;
            }
        }
        return $totalArr;
    }


    private function buildHeadingsFromBreakdown()
    {
        $breakdown = $this->data['breakdown'];
        $breakdownHeader = [];
        foreach ($this->data['report'] as $row) {
            $breakdownHeader[] = $this->getBreakdownDateString($row, $breakdown);
        }
        return $breakdownHeader;
    }

    private function getBreakdownDateString(array $row, $breakdown)
    {

        if ($breakdown == 'monthly') {
            $periodDates = $row['period_dates'];
            $monthName = (new DateTime($periodDates['date_start']))->format('F');
            $dateString = Lang::get('datetime.month.' . $monthName, [], 'es');
        }
        if ($breakdown == 'weekly') {
            $periodDates = $row['period_dates'];
            $dateString = 'SEM. DEL ' .
                (new DateTime($periodDates['date_start']))->format('d/m') .
                ' AL ' .
                (new DateTime($periodDates['date_end']))->format('d/m');
        }
        if ($breakdown == 'quarterly') {
            $periodDates = $row['period_dates'];
            $dateString = (new DateTime($periodDates['date_start']))->format('M. Y') .
                ' A ' .
                (new DateTime($periodDates['date_end']))->format('M. Y');
        }
        if ($breakdown == 'yearly') {
            $periodDates = $row['period_dates'];
            $dateString = 'AÑO ' . (new DateTime($periodDates['date_start']))->format('Y');
        }
        if ($breakdown == 'historical') {
            $dateString = 'Histórico';
        }
        return $dateString;
    }


    protected function getPeriodColumnSize(): String
    {
        $reportType = $this->data['type'];
        if ($reportType ===  'sales_per_channel' || $reportType === 'proposals_per_channel') {
            return 4;
        }
        if ($reportType === 'quality_leads_per_channel') {
            return 3;
        }
    }

}
