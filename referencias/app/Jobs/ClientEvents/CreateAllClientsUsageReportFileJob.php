<?php

namespace App\Jobs\ClientEvents;

use DateTime;
use Exception;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Bus\Queueable;
use App\Models\ClientInteraction;
use Illuminate\Support\Collection;
use App\Services\API\ClientService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\ClientEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Exports\Reports\ClientyConfigurations\AllClientsUsageReportExport;
use App\Services\API\Views\Reports\ClientyConfigurations\ClientUsageReportService;


// Deprecado, no se usa más (17 Jul 2023)
class CreateAllClientsUsageReportFileJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $timeout = 180;
    

    public function handle()
    {

        // $lockKey = 'CreateAllClientsUsageReportFileJob:handle';
        // $lockIsGranted = resolve(LockHelper::class)->getLockByName($lockKey, 60);
        // if (!$lockIsGranted) {
        //     dump('LOCKED');
        //     return null;
        // }

        // $report = new Collection();
        // $service = resolve(ClientUsageReportService::class);
        // $clients = resolve(ClientService::class)->findAllEnabled();
        
        // $dateEnd = new DateTime('now');
        // $dateStart = (new DateTime('7 days ago'))->setTime(0, 0, 0);
        // $options = ['filters' => ['date_start' => $dateStart, 'date_end' => $dateEnd]];
        // foreach ($clients as $client) {
        //     $clientReportRow = $service->clientLevelReport($client, $options);
        //     $report->push($clientReportRow);
        //     resolve(LockHelper::class)->getLockByName($lockKey, 30);
        // }
        // resolve(LockHelper::class)->releaseLockByName($lockKey);

        // $filename = 'clienty-reporte-actividad-clientes.xlsx';
        // $export = new AllClientsUsageReportExport($report, $dateStart, $dateEnd);
        // $stored = $export->store($filename, 'local');
        // if (!$stored) {
        //     throw new Exception('Error while storing clienty-reporte-actividad-clientes.xlsx file');
        // }
        // $oldPath = Storage::disk('local')->path($filename);
        // $newPath = public_path($filename);
        // rename($oldPath, $newPath);
    }

}
