<?php

namespace App\Exports\Reports;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Services\Traits\GetClientFromRequest;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class SentProposalReportExport implements FromView, WithStyles
{

    use Exportable, GetClientFromRequest;

    private $data;
    private $clientLeadsCustomFields;


    public function __construct($data)
    {
        $this->data = $data;
        $this->clientLeadsCustomFields = $this->getLeadsCustomFields();
    }


    public function view(): View
    {
        $report = $this->buildReport();
        return view('api.exports.reports.sent_proposal_report', [
            'report' => $report,
            'leadsCustomFields' => $this->clientLeadsCustomFields,
        ]);
    }


    public function styles(Worksheet $sheet)
    {
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'color' => ['rgb' => 'CCCCCC', ],
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


    private function buildReport(): array
    {
        $report = [];
        
        $proposalStatusMap = ['opened' => 'Abierto', 'closed' => 'Cerrado'];
        $hasLeadsCustomFields = $this->clientLeadsCustomFields->isNotEmpty();

        foreach ($this->data as $proposal) {
            $leadContactEmails = implode(
                ', ',
                $proposal->lead->mainLeadContact->leadContactEmails->pluck('email')->toArray()
            );
            $leadContactPhones = implode(
                ', ',
                $proposal->lead->mainLeadContact->leadContactPhones->pluck('phone')->toArray()
            );

            $leadCustomFieldsValues = collect([]);
            if ($hasLeadsCustomFields) {
                foreach ($this->clientLeadsCustomFields as $leadCustomField) {
                    $leadCustomFieldValue = $proposal->lead->leadCustomFieldsValues->where(
                        'lead_custom_field_id', $leadCustomField->id
                    )->first();
                    // Quedan ordenados para la view, igual que los CustomFields que vienen del cliente.
                    $leadCustomFieldsValues->push($leadCustomFieldValue ? $leadCustomFieldValue->value : null);
                }
            }

            $proposalStatus = $proposalStatusMap[$proposal['status']] ?? '-';

            $row = [
                'status' => $proposalStatus,
                'amount' => $proposal['amount'],
                'lead_id' => $proposal->lead->id,
                'user_name' => $proposal->user->name,
                'quality' => $proposal->lead->quality,
                'company' => $proposal->lead->company,
                'description' => $proposal['description'],
                'lead_message' => $proposal->lead->message,
                'lead_contact_emails' => $leadContactEmails,
                'lead_contact_phones' => $leadContactPhones,
                'lead_status' => $proposal->lead->status->name,
                'leadCustomFieldsValues' => $leadCustomFieldsValues,
                'sent_date' => $proposal['sent_date']->format('d/m/Y'),
                'other_fields' => $proposal->lead->other_fields_string,
                'lead_create_date' => $proposal->lead->lead_created_at->format('d/m/y'),
                'tag_names' => implode(', ', $proposal->lead->tags->pluck('name')->toArray()),
                'aquisition_channel_name' => $proposal->lead->acquisitionChannel->name ?? null,
                'landing_url' => $proposal->lead->landing ? $proposal->lead->landing->url : null,
                'utm_source' => $proposal->lead->utm_source,
                'utm_medium' => $proposal->lead->utm_medium,
                'utm_content' => $proposal->lead->utm_content,
                'utm_campaign' => $proposal->lead->utm_campaign,
                'utm_keywords' => $proposal->lead->utm_keywordss
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
