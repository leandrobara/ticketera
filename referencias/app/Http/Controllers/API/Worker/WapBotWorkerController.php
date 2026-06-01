<?php

namespace App\Http\Controllers\API\Worker;

use Throwable;
use Exception;
use App\Models\Client;
use App\Models\WapBot;
use App\Helpers\LockHelper;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\API\LeadService;
use App\Services\API\WapBot\WapBotService;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WapBot\WapBotConversationService;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;


class WapBotWorkerController extends BaseAPIController
{

    private const WINDOW_MINUTES = 60;


    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
    }


    public function sendFollowUpMessages(Request $request)
    {
        $lockKey = 'WapBotWorkerController:sendFollowUpMessages';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 90)) {
            die('Locked');
        }

        $this->printTitle('WapBot Follow-Up Messages');
        $this->printInfo('Buscando WapBots con follow-up configurado...');

        $wapBotService = resolve(WapBotService::class);
        $wapBotConversationService = resolve(WapBotConversationService::class);

        $wapBots = $wapBotService->findEnabledWithFollowUp();
        $this->printInfo("WapBots encontrados: {$wapBots->count()}");

        if ($wapBots->isEmpty()) {
            $this->printInfo('No hay WapBots con follow-up configurado.');
            resolve(LockHelper::class)->releaseLockByName($lockKey);
            return;
        }

        foreach ($wapBots as $wapBot) {
            SystemHelper::doFlush();
            $this->printWapBotInfo($wapBot);
            
            try {
                $conversationsToFollowUp = $wapBotConversationService->findConversationsNeedingFollowUp(
                    $wapBot, self::WINDOW_MINUTES
                );

                $count = $conversationsToFollowUp->count();
                $this->printInfo("Conversaciones pendientes de follow-up: {$count}");
                if ($count === 0) {
                    $this->printInfo('No hay conversaciones para enviar follow-up.');
                    continue;
                }

                foreach ($conversationsToFollowUp as $wapBotConversation) {
                    $this->printInfo("Despachando job para conversación: {$wapBotConversation->id}");
                    resolve(WhatsAppEventsDispatcherService::class)->dispatchWapBotSendFollowUpMessageJob(
                        $wapBot, $wapBotConversation
                    );
                }
                $this->printSuccess("Jobs despachados: {$count}");
            } catch (Throwable $e) {
                $this->printError("Error: {$e->getMessage()}");
                report($e);
            }

            resolve(LockHelper::class)->getLockByName($lockKey, 90);
            $this->printSeparator();
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
        $this->printSuccess('Proceso finalizado.');
    }


    public function autoCreateLeads(Request $request)
    {
        $lockKey = 'WapBotWorkerController:autoCreateLeads';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 90)) {
            die('Locked');
        }

        $this->printTitle('WapBot Auto-Create Leads');
        $this->printInfo('Buscando WapBots con auto_create_lead_after_minutes configurado...');

        $leadService = resolve(LeadService::class);
        $wapBotService = resolve(WapBotService::class);
        $wapBotConversationService = resolve(WapBotConversationService::class);

        $wapBots = $wapBotService->findEnabledWithEnabledLeadsAutoCreation();
        $this->printInfo("WapBots encontrados: {$wapBots->count()}");

        if ($wapBots->isEmpty()) {
            $this->printInfo('No hay WapBots con auto-create lead configurado.');
            resolve(LockHelper::class)->releaseLockByName($lockKey);
            return;
        }

        foreach ($wapBots as $wapBot) {
            SystemHelper::doFlush();
            $this->printWapBotInfo($wapBot);

            try {
                $conversations = $wapBotConversationService->findConversationsForAutoLeadCreation(
                    $wapBot, self::WINDOW_MINUTES
                );

                $count = $conversations->count();
                $this->printInfo("Conversaciones pendientes de auto-create lead: {$count}");
                if ($count === 0) {
                    $this->printInfo('No hay conversaciones para auto-crear leads.');
                    continue;
                }

                foreach ($conversations as $conversation) {
                    try {
                        $this->printInfo("Procesando conversación: {$conversation->id}");

                        // Crear lead
                        $newLead = $leadService->createFromWapBot($wapBot, $conversation);
                        $this->printInfo("Lead creado: {$newLead->id}");

                        // Marcar conversación como finalizada
                        $conversation->leadId = $newLead->id;
                        $wapBotConversationService->markAsEndedByLeadAutoCreationCron($conversation);
                        $wapBotConversationService->save($conversation);

                        if ($newLead?->alreadyExists) {
                            $this->printSuccess("Lead {$newLead->id} YA EXISTE para conversación {$conversation->id}");
                        } else {
                            $this->printSuccess("Lead {$newLead->id} CREADO para conversación {$conversation->id}");
                        }
                    } catch (Throwable $e) {
                        $this->printError("Error en conversación {$conversation->id}: {$e->getMessage()}");
                        report($e);
                    }
                }
            } catch (Throwable $e) {
                $this->printError("Error: {$e->getMessage()}");
                report($e);
            }

            resolve(LockHelper::class)->getLockByName($lockKey, 90);
            $this->printSeparator();
        }

        resolve(LockHelper::class)->releaseLockByName($lockKey);
        $this->printSuccess('Proceso finalizado.');
    }


    private function printTitle(string $title): void
    {
        echo "<h1 style='color: #333; border-bottom: 2px solid #333; padding-bottom: 10px;'>{$title}</h1>";
    }


    private function printSeparator(): void
    {
        echo "<br/><hr style='border: 1px dashed #ccc;'/><br/>";
    }


    private function printInfo(string $message): void
    {
        echo "<p style='color: #666; margin: 5px 0;'><i class='fa fa-info-circle'></i> {$message}</p>";
    }


    private function printSuccess(string $message): void
    {
        echo "<p style='color: green; font-weight: bold; margin: 5px 0;'>✓ {$message}</p>";
    }


    private function printError(string $message): void
    {
        echo "<p style='color: red; font-weight: bold; margin: 5px 0;'>✗ {$message}</p>";
    }


    private function printWapBotInfo(WapBot $wapBot): void
    {
        $client = $wapBot->client;
        $clientInfo = $client ? "Client ID: {$client->id} [<u>{$client->name}</u>]" : 'Client no disponible';
        echo "<h3 style='color: #0066cc; margin: 15px 0 5px 0;'>WapBot ID: {$wapBot->id} - {$clientInfo}</h3>";
        echo "<p style='color: #888; margin: 0 0 10px 0; font-size: 0.9em;'>";
        echo "Phone: {$wapBot->meta_phone_number_id} | ";
        echo "<b>Delay: {$wapBot->followup_1_delay_minutes} min</b>";
        echo "</p>";
    }

}
