<?php

namespace App\Exports\Reports;

use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class UTMContentTraceReportExport implements FromView, WithProperties, WithStyles
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
            'creator'        => 'Clienty',
            'lastModifiedBy' => 'Clienty',
            'title'          => 'Trazabilidad por Anuncios de Clienty.co',
            'description'    => 'Trazabilidad por Anuncios de Clienty.co',
            'subject'        => 'Trazabilidad por Anuncios de Clienty.co',
            'keywords'       => 'godixital,reporte,consultas,campañas',
            'category'       => 'Reporte',
            'company'        => 'Clienty'
        ];
    }


    public function view(): View
    {
        return view('api.exports.reports.utm_content_report', [
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
