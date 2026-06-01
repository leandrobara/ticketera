<?php

namespace App\Exports;

use DateTime;
use DateTimeZone;
use App\Models\Lead;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Services\Traits\GetClientFromRequest;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class LeadExport implements FromView, WithProperties, WithStyles
{

    use Exportable, GetClientFromRequest;

    private $leads;
    private $clientLeadsCustomFields;
    private array $trackingParameterKeys = [];
    private array $leadsFlattenedTrackingParameters = [];


    public function __construct(Collection $leads, Collection $leadsStatusEvents)
    {
        $this->leads = $leads;
        $this->leadsStatusEvents = $leadsStatusEvents;

        $leadsCustomFields = $this->getClient()->leadsCustomFields;
        if (!$this->getClient()->clientSettings->enable_leads_custom_fields) {
            $leadsCustomFields = collect();
        }
        $this->clientLeadsCustomFields = $leadsCustomFields;
    }


    public function properties(): array
    {
        return [
            'creator' => 'Clienty',
            'company' => 'Clienty',
            'category' => 'Reporte',
            'lastModifiedBy' => 'Clienty',
            'title' => 'Reporte Potenciales Clientes de Godixital.com',
            'subject' => 'Reporte Potenciales Clientes de Godixital.com',
            'description' => 'Reporte Potenciales Clientes de Godixital.com',
            'keywords' => 'godixital,reporte,consultas,potenciales,clientes,leads',
        ];
    }


    public function view(): View
    {
        return view('api.exports.lead_export', [
            'report' => $this->buildReport(),
            'leadsCustomFields' => $this->clientLeadsCustomFields,
            'trackingParameterKeys' => $this->trackingParameterKeys,
        ]);
    }


    public function styles(Worksheet $sheet)
    {
        $lastLetter = 'W';
        $customFieldsCount = $this->clientLeadsCustomFields->count();
        $trackingParameterKeysCount = count($this->trackingParameterKeys);
        $totalColumnsCount = ord($lastLetter) - ord('A') + $customFieldsCount + $trackingParameterKeysCount;
        $lastLetter = $this->getColumnLetterFromNumber($totalColumnsCount);
        
        $sheet->getStyle("A1:{$lastLetter}1")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);
        $sheet->getStyle("A1:{$lastLetter}1")->getAlignment()->applyFromArray([
            'wrapText' => true,
            'textRotation' => 0,
            'vertical' => Alignment::VERTICAL_CENTER,
            'horizontal' => Alignment::HORIZONTAL_LEFT,
        ]);

        for ($i = 1; $i <= $totalColumnsCount; $i++) {
            $columnIdentifier = $this->getColumnLetterFromNumber($i);
            $sheet->getColumnDimension($columnIdentifier)->setAutoSize(true);
        }
    }


    private function getColumnLetterFromNumber(int $number): string
    {
        $letters = '';
        while ($number >= 0) {
            $remainder = $number % 26;
            $letters = chr(65 + $remainder) . $letters;
            $number = intval($number / 26) - 1;
        }
        return $letters;
    }


    private function lastColumnLetter(int $totalColumnsCount): string
    {
        $result = '';
        $n = $totalColumnsCount - ord('A');
        while ($n > 0) {
            $n--;
            $result = chr(65 + ($n % 26)) . $result;
            $n = intdiv($n, 26);
        }
        return $result;
    }


    public function buildReport(): array
    {
        $this->extractTrackingParameterKeysAndFlattenValues();

        $report = [];
        $hasLeadsCustomFields = $this->clientLeadsCustomFields->isNotEmpty();

        foreach ($this->leads as $lead) {
            $createdAt = $lead->lead_created_at;
            $clientTz = new DateTimeZone($this->getClient()->timezone);
            $createdAt = (new DateTime($createdAt))->setTimezone($clientTz)->format('d/m/Y H:i') . ' hs.';

            $timeSinceLastStatusLegend = $this->getTimeSinceLastStatusLegend($lead);

            $leadCustomFieldsValues = collect([]);
            if ($hasLeadsCustomFields) {
                foreach ($this->clientLeadsCustomFields as $leadCustomField) {
                    $leadCustomFieldValue = $lead->leadCustomFieldsValues->where(
                        'lead_custom_field_id', $leadCustomField->id
                    )->first();
                    // Quedan ordenados para la view, igual que los CustomFields que vienen del cliente.
                    $leadCustomFieldsValues->push($leadCustomFieldValue ? $leadCustomFieldValue->value : null);
                }
            }

            $leadContactPhones = $lead->leadContactPhones->sortBy('lead_contact_id');
            $leadContactEmails = $lead->leadContactEmails->sortBy('lead_contact_id');

            $names = $this->getValuesCommaSeparated($lead->leadContacts, 'name');
            $emails = $this->getValuesCommaSeparated($leadContactEmails, 'email');
            $phones = $this->getValuesCommaSeparated($leadContactPhones, 'phone');
            $lastNames = $this->getValuesCommaSeparated($lead->leadContacts, 'last_name');

            $utmMedium = is_numeric($lead->utm_medium) ? "\u{200B}" . $lead->utm_medium : $lead->utm_medium;
            $utmSource = is_numeric($lead->utm_source) ? "\u{200B}" . $lead->utm_source : $lead->utm_source;
            $utmContent = is_numeric($lead->utm_content) ? "\u{200B}" . $lead->utm_content : $lead->utm_content;
            $utmKeywords = is_numeric($lead->utm_keywords) ? "\u{200B}" . $lead->utm_keywords : $lead->utm_keywords;
            $utmCampaign = is_numeric($lead->utm_campaign) ? "\u{200B}" . $lead->utm_campaign : $lead->utm_campaign;

            $row = [
                'id' => $lead->id,
                'contact_name' => $names,
                'method' => $lead->method,
                'created_at' => $createdAt,
                'message' => $lead->message,
                'company' => $lead->company,
                'quality' => $lead->quality,
                'user_name' => $lead->user->name,
                'lead_contact_emails' => $emails,
                'lead_contact_phones' => $phones,
                'contact_last_name' => $lastNames,
                'status_name' => $lead->status->name,
                'other_fields' => $lead->other_fields_string,
                'leadCustomFieldsValues' => $leadCustomFieldsValues,
                'timeSinceLastStatusLegend' => $timeSinceLastStatusLegend,
                'notes' => implode(',', $lead->notes->pluck('text')->toArray()),
                'tag_names' => implode(', ', $lead->tags->pluck('name')->toArray()),
                'landing_url' => $lead->landing ? $lead->landing->url : 'sin landing',
                'acquisition_channel_name' => $lead->acquisitionChannel->name ?? 'sin canal',
                'utm_medium' => $utmMedium,
                'utm_source' => $utmSource,
                'utm_content' => $utmContent,
                'utm_keywords' => $utmKeywords,
                'utm_campaign' => $utmCampaign,
                'trackingParameterValues' => $this->leadsFlattenedTrackingParameters[$lead->id] ?? [],

            ];

            foreach ($row as $key => $value) {
                if ($this->isCollection($value) || is_array($value)) {
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


    private function extractTrackingParameterKeysAndFlattenValues(): void
    {
        $uniqueKeys = [];
        foreach ($this->leads as $lead) {
            $flattened = Arr::dot($lead->tracking_parameters ?? []);
            $this->leadsFlattenedTrackingParameters[$lead->id] = $flattened;
            foreach (array_keys($flattened) as $key) {
                $uniqueKeys[$key] = true;
            }
        }
        $this->trackingParameterKeys = array_keys($uniqueKeys);
        sort($this->trackingParameterKeys);
    }


    protected function getTimeSinceLastStatusLegend(Lead $lead): string
    {
        $lastLastStatusEvent = $this->leadsStatusEvents->get($lead->id)?->last();
        if (!$lastLastStatusEvent) {
            return '-';
        }

        $dateNow = new DateTime();
        $eventTime = (new DateTime())->setTimestamp($lastLastStatusEvent['createdAtTs']);
        $daydiff = $eventTime->diff($dateNow)->format('%a');
        $hourDiff = $eventTime->diff($dateNow)->format('%h');
        $minutesDiff = $eventTime->diff($dateNow)->format('%i');
        if ($daydiff > 0) {
            $timeSinceLastStatusLegend = "${daydiff} días";
        } elseif ($hourDiff == 1) {
            $timeSinceLastStatusLegend = "0 días (${hourDiff} hora)";
        } elseif ($hourDiff > 0) {
            $timeSinceLastStatusLegend = "0 días (${hourDiff} horas)";
        } else {
            $timeSinceLastStatusLegend = "0 días (${minutesDiff} minutos)";
        }
        return $timeSinceLastStatusLegend;
    }


    protected function sanitizeString($value)
    {
        if (is_string($value)) {
            // Para evitar error al querer hacer cálculo de fórmula por empezar con =
            if (Str::startsWith($value, '=')) {
                $value = Str::replaceFirst('=', '-', $value);
            }

            // Quitar caracteres especiales
            $value = preg_replace(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F]|\xED[\xA0-\xBF].|\xEF\xBF[\xBE\xBF]/',
                "\xEF\xBF\xBD",
                $value
            );
        }

        return $value;
    }


    protected function getValuesCommaSeparated(Collection $collection, string $key): string
    {
        return $collection->filter(function ($obj) use ($key) {
            return !is_null($obj->$key);
        })->pluck($key)->implode(', ');
    }


    protected function isCollection($value)
    {
        return $value instanceof Collection;
    }

}
