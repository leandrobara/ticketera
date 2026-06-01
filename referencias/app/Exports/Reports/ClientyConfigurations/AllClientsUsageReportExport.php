<?php

namespace App\Exports\Reports\ClientyConfigurations;

use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class AllClientsUsageReportExport implements FromView, WithProperties, WithStyles
{

    use Exportable;

    private $report;


    public function __construct($report, $dateStart, $dateEnd)
    {
        $this->report = $report;
        $this->dateEnd = $dateEnd;
        $this->dateStart = $dateStart;
    }


    public function properties(): array
    {
        return [
            'creator'        => 'Clienty',
            'lastModifiedBy' => 'Clienty',
            'title'          => 'Reporte de actividad de clientes de Clienty.co',
            'description'    => 'Reporte de actividad de clientes de Clienty.co',
            'subject'        => 'Reporte de actividad de clientes de Clienty.co',
            'keywords'       => 'godixital,reporte,actividad,clientes',
            'category'       => 'Reporte',
            'company'        => 'Clienty'
        ];
    }


    public function view(): View
    {
        return view('api.exports.reports.clienty_configurations.all_clients_usage_report', [
            'report' => $this->report,
            'dateEnd' => $this->dateEnd,
            'dateStart' => $this->dateStart,
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
        $sheet->getStyle('A1:Z1')->applyFromArray($styleArray);
    }

}
