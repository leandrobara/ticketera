<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Artisan;
use App\Models\WhatsAppMetaAPIConnection;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;


class WhatsAppMetaAPITemplatesSetWABAIdCommand extends Command
{

    protected $signature = 'whatsapp-meta-api-templates:set-waba-id';
    protected $description = 'Set WABA Id for WhatsApp Meta API Templates';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        // Solo una por cada waba_id
        $wapConnections = WhatsAppMetaAPIConnection::whereIn('id', function ($query) {
            $query->select(\DB::raw('MIN(id)'))
                ->from('WhatsAppMetaAPIConnections')
                ->groupBy('waba_id');
        })->get();
        
        $wapMetaAPIService = resolve(WhatsAppMetaAPIService::class);
        foreach ($wapConnections as $wapConnection) {
            try {
                $opts = ['logger' => $this];
                $wapMetaAPIService->setWabaIdToUserWhatsAppTemplates($wapConnection, $opts);
            } catch (Exception $e) {
                dump($e);
                $this->info("\n\n\n\n\n");
                continue;
            }
        }
    }


}
