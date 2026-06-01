<?php

namespace App\Exports\Reports;

use Illuminate\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Services\Traits\GetClientFromRequest;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class SalesFunnelReportExport implements FromView, WithProperties, WithStyles
{

    use Exportable, GetClientFromRequest;

    public function __construct(
        private readonly int $averageTicket,
        private readonly Collection $salesFunnelReport,
    ) {
        //
    }


    public function properties(): array
    {
        return [
            'creator'        => 'Clienty',
            'lastModifiedBy' => 'Clienty',
            'title'          => 'Reporte de Embudo de Ventas de Clienty.co',
            'description'    => 'Reporte de Embudo de Ventas de Clienty.co',
            'subject'        => 'Reporte de Embudo de Ventas de Clienty.co',
            'keywords'       => 'clienty,reporte,embudo,ventas',
            'category'       => 'Reporte',
            'company'        => 'Clienty'
        ];
    }


    public function view(): View
    {
        $report = $this->buildReport();
        return view('api.exports.reports.sales_funnel_report', [
            'report' => $report,
            'averageTicket' => $this->averageTicket,
        ]);
    }


    public function styles(Worksheet $sheet)
    {
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'color' => ['rgb' => 'CCCCCC'],
                    'borderStyle' => Border::BORDER_THIN,
                ]
            ]
        ];
        // add borders to the headers
        $sheet->getStyle('A1:E1')->applyFromArray($styleArray);
    }


    public function buildReport()
    {
        $report = [];

        foreach ($this->salesFunnelReport as $data) {
            $conversionRatePercentage = '-';
            if ($data['conversionRatePercentage'] != 0) {
                $conversionRatePercentage = isset($data['conversionRatePercentage']) ?
                    number_format($data['conversionRatePercentage'], 1) :
                    null
                ;
            }

            $report[] = [
                'leadsCount' => $data['leadsCount'] ?? null,
                'conversionRatePercentage' => $conversionRatePercentage,
                'expectedTicketAmount' => $data['expectedTicketAmount'] ?? null,
                'statusCategoryName' => $data['statusCategory']['name'] ?? null,
                'saleProbabilityPercentage' => $data['saleProbabilityPercentage'] ?? null,
            ];
        }

        return $report;
    }

}
