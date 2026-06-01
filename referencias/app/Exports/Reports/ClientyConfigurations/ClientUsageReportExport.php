<?php

namespace App\Exports\Reports\ClientyConfigurations;

use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class ClientUsageReportExport implements FromView, WithProperties, WithStyles
{

    use Exportable;

    private $data;


    public function __construct($data)
    {
        $this->data = $data;
    }


    public function properties(): array
    {
        return [
            'company'        => 'Clienty',
            'creator'        => 'Clienty',
            'lastModifiedBy' => 'Clienty',
            'category'       => 'Reporte',
            'keywords'       => 'godixital,reporte,actividad,clientes',
            'title'          => 'Reporte de actividad de clientes de Clienty.co',
            'description'    => 'Reporte de actividad de clientes de Clienty.co',
            'subject'        => 'Reporte de actividad de clientes de Clienty.co',
        ];
    }


    public function view(): View
    {
        return view('api.exports.reports.clienty_configurations.client_usage_report', [
            'report' => $this->data
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
