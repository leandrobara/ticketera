<?php

namespace App\Exports\Reports\ClientyConfigurations;

use Illuminate\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class ClientPricingReportExport implements FromView, WithProperties, WithStyles
{

    use Exportable;

    private $clients;


    public function __construct($clients)
    {
        $this->clients = $clients;
    }


    public function properties(): array
    {
        return [
            'company'        => 'Clienty',
            'creator'        => 'Clienty',
            'lastModifiedBy' => 'Clienty',
            'category'       => 'Reporte',
            'keywords'       => 'godixital,reporte,pricing,clientes',
            'title'          => 'Reporte de pricing de clientes de Clienty.co',
            'description'    => 'Reporte de pricing de clientes de Clienty.co',
            'subject'        => 'Reporte de pricing de clientes de Clienty.co',
        ];
    }


    public function view(): View
    {
        $report = new Collection();
        foreach ($this->clients as $client) {
            $clientSettings = $client->clientSettings;
            $data['name'] = $client->name;
            $data['usersCount'] = $client->users->count();
            $data['landingCount'] = $client->landings->count();
            $data['bonusUsers'] = $clientSettings->bonus_users;
            $data['acquiredUsers'] = $clientSettings->acquired_users;
            $data['enabledUsersCount'] = $client->enabledUsers->count();
            $data['acquiredLandings'] = $clientSettings->acquired_landings;
            $data['acquiredExtraEmailsSendings'] = $clientSettings->acquired_extra_emails_sendings;

            $data['enabledWAPI'] = $clientSettings->enable_wapi;
            $data['enabledWapSender'] = $clientSettings->enable_whatsapp_sender_extension;

            $report->push($data);
        }

        return view('api.exports.reports.clienty_configurations.client_pricing_report', [
            'report' => $report
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A:G')->getAlignment()->applyFromArray([
            'wrapText' => true,
            'textRotation' => 0,
            'vertical' => Alignment::VERTICAL_CENTER,
        ]);
        //columns A:F
        for ($i = 65; $i <= 72; $i++) {
            $column = chr($i);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

}
