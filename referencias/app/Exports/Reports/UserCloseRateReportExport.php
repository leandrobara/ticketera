<?php

namespace App\Exports\Reports;

use DateTime;
use Illuminate\View\View;
use App\Services\API\UserService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Maatwebsite\Excel\Concerns\FromView;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class UserCloseRateReportExport implements WithProperties, FromView, WithStyles
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
            'title'          => 'Clienty Reporte Tasa de Cierre de vendedores',
            'description'    => 'Clienty Reporte Tasa de Cierre de vendedores',
            'subject'        => 'Clienty Reporte Tasa de Cierre de vendedores',
            'keywords'       => 'clienty,reporte,vendedores',
        ];
    }


    public function view(): View
    {
        $report = $this->buildReport();
        $reportTotals = $this->buildTotals($report);

    
        return view('api.exports.reports.user_close_rate_report.user_close_rate_report', [
            'report' => $report,
            'reportTotals' => $reportTotals,
            'headers' => $this->buildHeadingsFromBreakdown(),
        ]);
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
        
        $periodColumnSize = 4;
        $periodsCount = count($this->data['report']);
        $lastRowNumber = count($this->data['report'][0]['users']) + 3;
        
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
        $users = resolve(UserService::class)->findAll()->keyBy('id');
        $userPeriods = collect($this->data['report'])->pluck('users');

        foreach ($userPeriods as $userPeriod) {
            $usersInfo = collect($userPeriod)->reverse();
            foreach ($usersInfo as $userInfo) {
                $user = $users->get($userInfo['user_id']);
                $userName = trim($user->name . ' ' . $user->last_name);
                $userInfo = $this->addRateToUserInfo($userInfo);
                $report[$userName][] = $userInfo;
            }
        }
        return $report;
    }


    protected function addRateToUserInfo(array $userInfo): array
    {
        $leadsCount = $userInfo['leads_count'];

        $rate = intval($userInfo['unique_sales_count'] * 100 / ($leadsCount ?: 1));
        $userInfo['sales_rate'] = $rate;
        return $userInfo;
    }


    protected function buildTotals(array $excelData): array
    {
        $first = reset($excelData);
        $periodsCount = count($first);

        $totalArr = [];
        for ($periodIndex = 0; $periodIndex < $periodsCount; $periodIndex++) {
            $singlePeriodUsersInfo = array_map(function ($userPeriods) use ($periodIndex) {
                return $userPeriods[$periodIndex];
            }, $excelData);
            
            $singlePeriodUsersInfo = collect($singlePeriodUsersInfo);
            $totalLeadsCount = $singlePeriodUsersInfo->sum('leads_count');
            $totalArr[$periodIndex] = ['leads_count' => $totalLeadsCount];

            $totalSalesCount = $singlePeriodUsersInfo->sum('total_sales_count');
            $totalUniqueSalesCount = $singlePeriodUsersInfo->sum('unique_sales_count');
            $totalSalesRate = intval($totalUniqueSalesCount * 100 / ($totalLeadsCount ?: 1));
            $totalArr[$periodIndex]['sales_rate'] = $totalSalesRate;
            $totalArr[$periodIndex]['total_sales_count'] = $totalSalesCount;
            $totalArr[$periodIndex]['unique_sales_count'] = $totalUniqueSalesCount;
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

}
