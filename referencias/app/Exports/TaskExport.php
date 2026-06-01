<?php

namespace App\Exports;

use DateTime;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\WithProperties;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class TaskExport implements FromView, WithProperties, WithStyles
{

    use Exportable;

    private $data;


    public function __construct(LengthAwarePaginator $data)
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
            'title'          => 'Export de tareas',
            'description'    => 'Export de tareas',
            'subject'        => 'Export de tareas',
            'keywords'       => 'godixital,reporte,tareas,leads',
        ];
    }


    public function view(): View
    {
        return view('api.exports.task_export', ['report' => $this->buildReport()]);
    }


    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:L1')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC', ]
                ]
            ]
        ]);

        $sheet->getStyle('A:L')->getAlignment()->applyFromArray([
            'vertical'     => Alignment::VERTICAL_CENTER,
            'horizontal'   => Alignment::HORIZONTAL_LEFT,
            'textRotation' => 0,
            'wrapText'     => true
        ]);

        //columns A:I
        for ($i = 65; $i <= 84; $i++) {
            $column = chr($i);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }


    public function buildReport(): array
    {
        $report = [];
        $tasks = $this->data->getCollection()->sortByDesc('created_at');

        foreach ($tasks as $task) {
            if (!$task->lead) {
                continue;
            }

            $task->load([
                'lead.status' => function ($q) {
                    $q->withTrashed();
                },
                'user' => function ($q) {
                    $q->withTrashed();
                },
            ]);

            $tags = $task->lead->tags->implode(function ($tag, $key) {
                return $tag['name'];
            }, ', ');

            $clientTz = new DateTimeZone($task->client->timezone);
            $createdAt = (new DateTime($task->created_at))->setTimezone($clientTz);
            $limitDate = (new DateTime($task->limit_date))->setTimezone($clientTz);

            $report[] = [
                'task' => $task,
                'tags' => $tags,
                'createdAt' => $createdAt->format('d/m/Y H:i'),
                'limitDate' => $limitDate->format('d/m/Y H:i'),
                'leadContactEmails' => $task->lead->mainLeadContact->leadContactEmails->implode('email', ', '),
                'leadContactPhones' => $task->lead->mainLeadContact->leadContactPhones->implode('phone', ', '),
            ];
        }

        return $report;
    }
}
