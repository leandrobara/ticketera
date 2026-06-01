<?php

namespace App\Jobs\ClientEvents;

use Throwable;
use App\Models\Client;
use App\Models\BusinessArea;
use App\Helpers\RedisHelper;
use Illuminate\Bus\Queueable;
use App\Models\BusinessAreaChild;
use Illuminate\Support\Collection;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\API\BusinessAreaService;
use App\Services\API\WhatsAppTemplateService;
use App\Jobs\ClientEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\ClientyConfigWhatsAppTemplateService;


class CreateClientWhatsAppTemplatesByBusinessAreaJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;


    public function __construct(
        public readonly int $clientId,
        public readonly string|null $leadsBusinessAreaName,
        public readonly string|null $leadsBusinessAreaChildName
    ) {
    }


    public function handle()
    {
        $clientyConfigWhatsAppTpls = new Collection();
        $clientyConfigTplService = resolve(ClientyConfigWhatsAppTemplateService::class);
        
        $clientyConfigWhatsAppTplsForAll = $clientyConfigTplService->findForAllBusinessArea();
        $clientyConfigWhatsAppTpls = $clientyConfigWhatsAppTpls->merge($clientyConfigWhatsAppTplsForAll);
        
        if ($this->leadsBusinessAreaName) {
            $businessArea = resolve(BusinessAreaService::class)->findOneByName($this->leadsBusinessAreaName);
            if ($businessArea) {
                $businessAreaTpls = $clientyConfigTplService->findByBusinessAreaWithNoChild($businessArea);
                $clientyConfigWhatsAppTpls = $clientyConfigWhatsAppTpls->merge($businessAreaTpls);

                if ($this->leadsBusinessAreaChildName) {
                    $childHash = BusinessAreaChild::buildHash($this->leadsBusinessAreaChildName);
                    $businessAreaChild = $businessArea->businessAreaChildren->where('hash', $childHash)->first();
                    if ($businessAreaChild) {
                        $childTpls = $clientyConfigTplService->findByBusinessAreaChild($businessAreaChild);
                        $clientyConfigWhatsAppTpls = $clientyConfigWhatsAppTpls->merge($childTpls);
                    }
                }
            }
        }
        if ($clientyConfigWhatsAppTpls->isEmpty()) {
            return;
        }

        $client = Client::findOrFail($this->clientId);
        $whatsAppTplService = resolve(WhatsAppTemplateService::class);
        $whatsAppTpls = $whatsAppTplService->createMultipleFromClientyConfigWhatsAppTemplates(
            $clientyConfigWhatsAppTpls, $client->users->first()
        );
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
