<?php

namespace App\Exports\Reports;

use DateTime;
use DateTimeZone;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Services\Traits\GetClientFromRequest;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class SalesHistoryReportExport implements FromView, WithProperties, WithStyles
{

    use Exportable, GetClientFromRequest;

    private $data;
    private $clientLeadsCustomFields;


    public function __construct($data)
    {
        $this->data = $data;
        $this->clientLeadsCustomFields = $this->getLeadsCustomFields();
    }


    public function properties(): array
    {
        return [
            'creator'        => 'Clienty',
            'lastModifiedBy' => 'Clienty',
            'title'          => 'Reporte de Ventas de Clienty.co',
            'description'    => 'Reporte de Ventas de Clienty.co',
            'subject'        => 'Reporte de Ventas de Clienty.co',
            'keywords'       => 'clienty,reporte,ventas',
            'category'       => 'Reporte',
            'company'        => 'Clienty'
        ];
    }


    public function view(): View
    {
        $report = $this->buildReport();
        return view('api.exports.reports.sales_history_report', [
            'report' => $report,
            'leadsCustomFields' => $this->clientLeadsCustomFields,
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


    private function getLeadsCustomFields(): Collection
    {
        $client = $this->getClient();
        $clientSettings = $client->clientSettings;
        $enableLeadsCustomFields = $clientSettings->enable_leads_custom_fields;

        if (!$enableLeadsCustomFields) {
            $leadsCustomFields = collect();
            return $leadsCustomFields;
        }

        $leadsCustomFields = $client->leadsCustomFields;
        return $leadsCustomFields;
    }


    public function buildReport()
    {
        $report = [];
        $clientTz = new DateTimeZone($this->getClient()->timezone);
        $hasLeadsCustomFields = $this->clientLeadsCustomFields->isNotEmpty();

        foreach ($this->data as $sale) {
            $leadContactEmails = implode(
                ', ',
                $sale->lead->mainLeadContact->leadContactEmails->pluck('email')->toArray()
            );
            $leadContactPhones = implode(
                ', ',
                $sale->lead->mainLeadContact->leadContactPhones->pluck('phone')->toArray()
            );

            $leadCustomFieldsValues = collect([]);
            if ($hasLeadsCustomFields) {
                foreach ($this->clientLeadsCustomFields as $leadCustomField) {
                    $leadCustomFieldValue = $sale->lead->leadCustomFieldsValues->where(
                        'lead_custom_field_id', $leadCustomField->id
                    )->first();
                    // Quedan ordenados para la view, igual que los CustomFields que vienen del cliente.
                    $leadCustomFieldsValues->push($leadCustomFieldValue ? $leadCustomFieldValue->value : null);
                }
            }

            $saleDate = (new DateTime($sale['sale_date']))->setTimezone($clientTz);

            $row = [
                'lead_id' => $sale->lead_id,
                'sale_amount' => $sale['amount'],
                'company' => $sale->lead->company,
                'quality' => $sale->lead->quality,
                'message' => $sale->lead->message,
                'status' => $sale->lead->status->name,
                'sale_user_name' => $sale->user->full_name,
                'sale_description' => $sale['description'],
                'lead_contact_name' => $sale->lead->mainLeadContact->name,
                'lead_contact_last_name' => $sale->lead->mainLeadContact->last_name,
                'lead_contact_emails' => $leadContactEmails,
                'lead_contact_phones' => $leadContactPhones,
                'lead_user_name' => $sale->lead->user->full_name,
                'sale_date' => $saleDate->format('d/m/Y'),
                'other_fields' => $sale->lead->other_fields_string,
                'leadCustomFieldsValues' => $leadCustomFieldsValues,
                'landing_url' => $sale->lead->landing ? $sale->lead->landing->url : '',
                'tag_names' => implode(', ', $sale->lead->tags->pluck('name')->toArray()),
                'aquisition_channel_name' => $sale->lead->acquisitionChannel->name ?? null,
            ];

            foreach ($row as $key => $val) {
                // Para evitar error al querer hacer cálculo de fórmula por empezar con =
                if (Str::startsWith($val, '=')) {
                    $row[$key] = Str::replaceFirst('=', '-', $row[$key]);
                }
                // Para quitar caracteres especiales que rompen el XML
                if (is_string($val)) {
                    $row[$key] = preg_replace(
                        '/[\x00-\x08\x0B\x0C\x0E-\x1F]|\xED[\xA0-\xBF].|\xEF\xBF[\xBE\xBF]/',
                        "\xEF\xBF\xBD",
                        $row[$key]
                    );
                }
            }

            $report[] = $row;
        }
        return $report;
    }

}
