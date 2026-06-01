<?php

namespace App\Exports\Reports;

use DateTime;
use Illuminate\View\View;
use Illuminate\Support\Collection;
use App\Services\API\StatusService;
use Illuminate\Support\Facades\Lang;
use Maatwebsite\Excel\Concerns\FromView;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class TagCloseRateReportExport implements WithProperties, FromView, WithStyles
{

    use Exportable;

    private $data;


    public function __construct(Collection $data)
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
            'keywords'       => 'clienty,reporte,etiquetas',
            'title'          => 'Clienty Reporte Tasa de Cierre según etiquetas',
            'description'    => 'Clienty Reporte Tasa de Cierre según etiquetas',
            'subject'        => 'Clienty Reporte Tasa de Cierre según etiquetas',
        ];
    }


    public function view(): View
    {
        $report = $this->data;
        foreach ($report as $i => $reportRow) {
            $report[$i] = $this->addSalesRate($reportRow);
        }
        $reportTotals = $this->buildTotals($report);
    
        return view('api.exports.reports.tag_close_rate_report.tag_close_rate_report', [
            'report' => $report,
            'reportTotals' => $reportTotals,
        ]);
    }


    public function styles(Worksheet $sheet)
    {
        $borderAllStyles = [
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
        
        $lastRowNumber = $this->data->count() + 2;
        $sheet->getStyle("A1:E1")->applyFromArray($borderTopBottomStyle);
        // Add borders to the totals row
        $sheet->getStyle("A1:A{$lastRowNumber}")->applyFromArray($borderRightStyle);
        $sheet->getStyle("E1:E{$lastRowNumber}")->applyFromArray($borderRightStyle);
        $sheet->getStyle("A{$lastRowNumber}:E{$lastRowNumber}")->applyFromArray($borderAllStyles);
    }


    protected function addSalesRate(array $reportRow): array
    {
        $rate = 0;
        if ($reportRow['leads_count']) {
            $rate = intval($reportRow['unique_sales_count'] * 100 / ($reportRow['leads_count'] ?: 1));
        }
        $reportRow['sales_rate'] = $rate;
        return $reportRow;
    }


    protected function buildTotals(Collection $report): array
    {
        $totalLeadsCount = $report->sum('leads_count');
        $totalSalesCount = $report->sum('total_sales_count');
        $totalUniqueSalesCount = $report->sum('unique_sales_count');
        $totalSalesRate = intval($totalUniqueSalesCount * 100 / ($totalLeadsCount ?: 1));
        
        $arr['sales_rate'] = $totalSalesRate;
        $arr['leads_count'] = $totalLeadsCount;
        $arr['total_sales_count'] = $totalSalesCount;
        $arr['unique_sales_count'] = $totalUniqueSalesCount;
        return $arr;
    }

}
