<?php

namespace App\Http\Controllers\API\Worker;

use DateTime;
use App\Models\Lead;
use App\Models\Client;
use Illuminate\Http\Request;
use App\Helpers\RedisHelper;
use App\Helpers\SystemHelper;
use App\DTO\Import\LeadsLeadDTO;
use Illuminate\Support\Collection;
use App\Helpers\ClientyMailerAPIHelper;
use App\Services\API\Views\EmailService;
use App\Services\API\Import\ImportLeadService;
use App\Http\Controllers\API\BaseAPIController;
use App\DTO\MailerQuickEmailScheduleRequestParametersDTO;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;
use App\Http\Requests\Worker\SendClientsEmailSendingMetricsReportRequest;


class ClientWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
    }


    public function clearAllCache(Request $req, Client $client)
    {
        resolve(RedisHelper::class)->deleteAll();
        return $this->getSuccessResponse(true);
    }


    public function clearAllClientCache(Request $req, Client $client)
    {
        $dispatcher = resolve(ClientEventsDispatcherService::class);
        $dispatcher->dispatchClearClientCacheJob($client);
        return $this->getSuccessResponse(['id' => $client->id]);
    }

    
    public function sendEmailSendingMetricsReport(SendClientsEmailSendingMetricsReportRequest $req)
    {
        SystemHelper::setManualFlush();
        
        $validated = $req->validated();
        $emailSendingMetricsDTOs = new Collection();
        $emailService = resolve(EmailService::class);
        $sendEmail = $validated['send_email'] ?? false;
        $dumpMetrics = $validated['dump_metrics'] ?? true;
        
        $clients = Client::where('enabled', true)->get();
        foreach ($clients as $client) {
            SystemHelper::doFlush();
            $metricsDTO = $emailService->getClientyConfigClientModalMetricsInfo($client, $validated);
            if ($metricsDTO->bouncedPercentage < 8 && $metricsDTO->complainedPercentage < 0.3) {
                continue;
            }
            $emailSendingMetricsDTOs->push($metricsDTO);

            if ($dumpMetrics) {
                echo "<h3>{$metricsDTO->client->name}</h3>";
                var_dump($metricsDTO->toArray());
                echo '<hr/>';
            }
        }

        $period = $validated['period'] ?? null;
        $body = view(
            'api.emails.client-notification.email-metrics-report', compact('emailSendingMetricsDTOs', 'period')
        )->render();
        echo $body;
        
        if ($sendEmail) {
            $data = [
                'body' => $body,
                'fromName' => 'Clienty CRM',
                'to' => ['facundo@godixital.com'],
                'from' => 'notifications@clienty.co',
                'appCustomId' => 'SYSTEM_client_email_sending_metrics',
                'sendDate' => (new DateTime('now'))->format('Y-m-d\\TH:i:sP'),
                'subject' => 'Clienty | Reporte envíos de email (SPAM y rebotes)',
            ];
            if (redirectEmails()) {
                $data['to'] = [config('emails.redirect_emails_to')];
            }
            $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
            $mailerResponseDTO = resolve(ClientyMailerAPIHelper::class)->scheduleQuickEmail($dto);

            echo '<hr><hr>';
            var_dump($mailerResponseDTO);
        }
    }



    // @todo Esto es temporal. Borrar cuando no se necesite.
    public function updateClientsLeadsUTMKeywords(Request $req)
    {
        SystemHelper::setManualFlush();

        $chunkLength = 150;
        $clientId = $req->input('client_id');
        $minLeadId = $req->input('min_lead_id');
        $leadIds = $req->input('lead_ids', []);
        
        $queryBuilder = Lead::where('utm_source', 'Adwords')
            ->whereNull('utm_keywords')
        ;
        if ($clientId) {
            $queryBuilder->where('client_id', $clientId);
        }
        if ($leadIds) {
            $queryBuilder->whereIn('id', $leadIds);
        }
        if ($minLeadId) {
            $queryBuilder->where('id', '>=', $minLeadId);
        }
        $totalLeadsCount = $queryBuilder->count();
        $totalLoops = (int) ($totalLeadsCount / $chunkLength);

        $loop = 1;
        $queryBuilder = Lead::where('utm_source', 'Adwords')
            ->whereNull('utm_keywords')
            ->orderBy('id', 'asc')
            ->select(['id', 'leads_query_id'])
        ;
        if ($clientId) {
            $queryBuilder->where('client_id', $clientId);
        }
        if ($leadIds) {
            $queryBuilder->whereIn('id', $leadIds);
        }
        if ($minLeadId) {
            $queryBuilder->where('id', '>=', $minLeadId);
        }
        $queryBuilder->chunk($chunkLength, function ($leads) use ($totalLoops, &$loop) {
            $leadsQueryIds = $leads->pluck('leads_query_id')->toArray();
            $leadsLeadDTOs = resolve(ImportLeadService::class)->getLeadsLeads(['leads_leads_ids' => $leadsQueryIds]);
            $leadsLeadDTOs = $leadsLeadDTOs->filter(function (LeadsLeadDTO $leadDTO) {
                return trim($leadDTO->utm_keywords);
            })->values();

            echo "<br>\n- Loop: {$loop} / {$totalLoops}. <br><br>\n\n";
            foreach ($leadsLeadDTOs as $leadsLeadDTO) {
                $updated = Lead::where('leads_query_id', $leadsLeadDTO->leadsId)->update([
                    'utm_keywords' => $leadsLeadDTO->utm_keywords
                ]);
                if ($updated) {
                    echo " -- Leads query ID: {$leadsLeadDTO->leadsId} updated. <br>\n";
                } else {
                    echo " -- Leads query ID: {$leadsLeadDTO->leadsId} ERROR. <br>\n";
                }
            }
            $loop++;
            SystemHelper::doFlush();
        });
    }

}
