<?php

namespace App\Exports;

use DateTime;
use DateTimeZone;
use App\Models\Email;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Http\Resources\LeadContactResource;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\WithProperties;
use App\Http\Resources\LeadContactEmailResource;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class MassiveEmailOpeningsExport implements FromView, WithProperties, WithStyles
{

    use Exportable;

    private $subject;
    private $emailNotificationLogs;


    public function __construct(Collection $emailNotificationLogs, $subject)
    {
        $this->subject = $subject;
        $this->emailNotificationLogs = $emailNotificationLogs;
    }


    public function properties(): array
    {
        return [
            'creator'        => 'Clienty',
            'lastModifiedBy' => 'Clienty',
            'title'          => 'Reporte aperturas email masivo de Clienty',
            'subject'        => 'Reporte aperturas email masivo de Clienty',
            'description'    => 'Reporte aperturas email masivo de Clienty',
            'keywords'       => 'clienty,reporte,consultas,emails,open',
            'category'       => 'Reporte',
            'company'        => 'Clienty',
        ];
    }


    public function view(): View
    {
        return view('api.exports.email_massive_open_export', [
            'report' => $this->buildReport(),
            'subject' => $this->subject
        ]);
    }


    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A:G')->getAlignment()->applyFromArray(
            [
                'vertical'     => Alignment::VERTICAL_CENTER,
                'textRotation' => 0,
                'wrapText'     => true
            ]
        );

        //columns A:G
        for ($i = 65; $i <= 72; $i++) {
            $column = chr($i);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }


    public function buildReport(): array
    {
        $report = [];
        foreach ($this->emailNotificationLogs as $emailNotificationLog) {
            $client = $this->emailNotificationLogs->first()->client;
            $clientTz = new DateTimeZone($client->timezone);
            $sentDate = (new DateTime($emailNotificationLog->email->created_at))->setTimezone($clientTz);
            $openedDate = (new DateTime($emailNotificationLog->created_at))->setTimezone($clientTz);

            $report[] = [
                'lead_id' => $emailNotificationLog->email->lead_id,
                'lead_company' => $emailNotificationLog->email->lead->company,
                'lead_contact_email' => $emailNotificationLog->email->leadContactEmail->email,
                'lead_contact_name' => $emailNotificationLog->email->leadContactEmail->leadContact->name,
                'lead_contact_last_name' => $emailNotificationLog->email->leadContactEmail->leadContact->last_name,
                'email_sent_date' => $sentDate,
                'email_opened_date' => $openedDate,
            ];
        }
        return $report;
    }

}
