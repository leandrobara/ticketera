<?php

namespace App\Exports;

use DateTime;
use DateTimeZone;
use App\Models\Email;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use App\Http\Resources\LeadResource;
use App\Models\EmailNotificationLog;
use Maatwebsite\Excel\Concerns\FromView;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Http\Resources\LeadContactResource;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Services\Traits\GetClientFromRequest;
use Maatwebsite\Excel\Concerns\WithProperties;
use App\Http\Resources\LeadContactEmailResource;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class MassiveEmailExport implements FromView, WithProperties, WithStyles
{

    use Exportable, GetClientFromRequest;

    private $collection;
    private $clientLeadsCustomFields;


    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
        
        $leadsCustomFields = $this->getClient()->leadsCustomFields;
        if (!$this->getClient()->clientSettings->enable_leads_custom_fields) {
            $leadsCustomFields = collect();
        }
        $this->clientLeadsCustomFields = $leadsCustomFields;
    }


    public function properties(): array
    {
        return [
            'creator'        => 'Clienty',
            'lastModifiedBy' => 'Clienty',
            'category'       => 'Reporte',
            'company'        => 'Clienty',
            'title'          => 'Reporte emails masivos de Clienty',
            'description'    => 'Reporte emails masivos de Clienty',
            'subject'        => 'Reporte emails masivos de Clienty',
            'keywords'       => 'clienty,reporte,consultas,emails',
        ];
    }


    public function view(): View
    {
        return view('api.exports.email_massive_export', [
            'report' => $this->buildReport(),
            'leadsCustomFields' => $this->clientLeadsCustomFields,
        ]);
    }


    public function styles(Worksheet $sheet)
    {
        $baseColumnsCount = 13; // Columnas base: A hasta M (13 columnas)
        $customFieldsCount = $this->clientLeadsCustomFields->count();
        $totalColumnsCount = $baseColumnsCount + $customFieldsCount;
        $lastLetter = Coordinate::stringFromColumnIndex($totalColumnsCount);
        
        $sheet->getStyle("A1:{$lastLetter}1")->getAlignment()->applyFromArray(
            [
                'vertical'     => Alignment::VERTICAL_CENTER,
                'textRotation' => 0,
                'wrapText'     => true
            ]
        );
        
        // Auto-size todas las columnas
        for ($i = 1; $i <= $totalColumnsCount; $i++) {
            $columnIdentifier = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($columnIdentifier)->setAutoSize(true);
        }
    }


    public function buildReport(): array
    {
        $report = [];
        $hasLeadsCustomFields = $this->clientLeadsCustomFields->isNotEmpty();

        foreach ($this->collection as $email) {
            $email = $this->loadLead($email);
            $email = $this->loadClient($email);
            $email = $this->loadLeadContact($email);
            $email = $this->loadLeadContactEmail($email);
            $email = $this->loadLeadCustomFieldsValues($email);

            $clientTz = new DateTimeZone($email->client->timezone);
            $openedDate = $email->opened_date;
            $bouncedDate = $email->bounced_date;
            $complainedDate = $email->complained_date;
            $unsubscribedDate = $email->unsubscribed_date;
            $opensCount = $email->openedEmailNotificationLogs->count();
            $mainPhone = $email->lead->main_phone ? $email->lead->main_phone : null;
            $openedDate = $openedDate ? (new DateTime($openedDate))->setTimezone($clientTz) : null;
            $bouncedDate = $bouncedDate ? (new DateTime($bouncedDate))->setTimezone($clientTz) : null;
            $complainedDate = $complainedDate ? (new DateTime($complainedDate))->setTimezone($clientTz) : null;
            $unsubscribedDate = $unsubscribedDate ? (new DateTime($unsubscribedDate))->setTimezone($clientTz) : null;
            
            $lastOpenedLog = $this->getLastOpenedLog($email);
            $firstOpenedLog = $this->getFirstOpenedLog($email);
            $lastOpenedDate = $lastOpenedLog ? $lastOpenedLog->created_at : null;
            $firstOpenedDate = $firstOpenedLog ? $firstOpenedLog->created_at : null;
            $lastOpenedDate = $lastOpenedDate ? (new DateTime($lastOpenedDate))->setTimezone($clientTz) : null;
            $firstOpenedDate = $firstOpenedDate ? (new DateTime($firstOpenedDate))->setTimezone($clientTz) : null;
            $lastOpenedDateStr = $lastOpenedDate ? $lastOpenedDate->format('d/m/Y H:i') : null;
            $firstOpenedDateStr = $firstOpenedDate ? $firstOpenedDate->format('d/m/Y H:i') : null;

            // Procesar custom fields del lead
            $leadCustomFieldsValues = collect([]);
            if ($hasLeadsCustomFields && $email->lead) {
                foreach ($this->clientLeadsCustomFields as $leadCustomField) {
                    $leadCustomFieldValue = $email->lead->leadCustomFieldsValues->where(
                        'lead_custom_field_id', $leadCustomField->id
                    )->first();
                    // Quedan ordenados para la view, igual que los CustomFields que vienen del cliente.
                    $leadCustomFieldsValues->push($leadCustomFieldValue ? $leadCustomFieldValue->value : null);
                }
            }

            $row = [
                'id' => $email->id,
                'lead_id' => $email->lead_id,
                'opens_count' => $opensCount,
                'lead_contact_phone' => $mainPhone,
                'email_opened_date' => $openedDate,
                'email_bounced_date' => $bouncedDate,
                'lead_company' => $email->lead->company,
                'email_complained_date' => $complainedDate,
                'email_unsubscribed_date' => $unsubscribedDate,
                'last_opened_date_formatted' => $lastOpenedDateStr,
                'leadCustomFieldsValues' => $leadCustomFieldsValues,
                'first_opened_date_formatted' => $firstOpenedDateStr,
                'external_massive_id' => $email->external_massive_id,
                'lead_contact_email' => $email->leadContactEmail->email,
                'lead_contact_name' => $email->leadContactEmail->leadContact->name,
                'lead_contact_last_name' => $email->leadContactEmail->leadContact->last_name,
            ];

            foreach ($row as $key => $value) {
                if ($this->isCollection($value)) {
                    foreach ($value as $subKey => $subValue) {
                        $row[$key][$subKey] = $this->sanitizeString($subValue);
                    }
                } else {
                    $row[$key] = $this->sanitizeString($value);
                }
            }

            $report[] = $row;
        }

        return $report;
    }


    private function loadLead(Email $email): Email
    {
        if (!$email->relationLoaded('lead')) {
            $email->load('lead');
        }
        return $email;
    }


    private function loadLeadCustomFieldsValues(Email $email): Email
    {
        if ($email->lead && !$email->lead->relationLoaded('leadCustomFieldsValues')) {
            $email->lead->load('leadCustomFieldsValues');
        }
        return $email;
    }


    private function loadLeadContactEmail(Email $email): Email
    {
        if (!$email->relationLoaded('leadContactEmail')) {
            $email->load('leadContactEmail');
        }
        return $email;
    }


    private function loadLeadContact(Email $email): Email
    {
        if (!$email->leadContactEmail->relationLoaded('leadContact')) {
            $email->leadContactEmail->load('leadContact');
        }
        return $email;
    }


    private function loadClient(Email $email): Email
    {
        if (!$email->relationLoaded('client')) {
            $email->load('client');
        }
        return $email;
    }


    private function getFirstOpenedLog(Email $email): ?EmailNotificationLog
    {
        $firstOpenLog = $email->openedEmailNotificationLogs()->orderBy('created_at', 'asc')->first();
        return $firstOpenLog;
    }


    private function getLastOpenedLog(Email $email): ?EmailNotificationLog
    {
        $lastOpenLog = $email->openedEmailNotificationLogs()->orderBy('created_at', 'desc')->first();
        return $lastOpenLog;
    }


    protected function sanitizeString($value)
    {
        if (is_string($value)) {
            // Para evitar error al querer hacer cálculo de fórmula por empezar con =
            if (Str::startsWith($value, '=')) {
                $value = Str::replaceFirst('=', '-', $value);
            }

            // Para quitar caracteres especiales que rompen el XML
            $value = preg_replace(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F]|\xED[\xA0-\xBF].|\xEF\xBF[\xBE\xBF]/',
                "\xEF\xBF\xBD",
                $value
            );
        }

        return $value;
    }


    protected function isCollection($value)
    {
        return $value instanceof Collection;
    }

}
