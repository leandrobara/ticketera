<?php

namespace App\Http\Controllers\API\Worker;

use DateTime;
use Throwable;
use Exception;
use App\Models\Client;
use App\Helpers\LockHelper;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Models\WhatsAppSending;
use App\Services\API\WAPIService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\API\ClientService;
use App\Services\API\WAPSenderService;
use App\Models\WhatsAppSendingMessage;
use App\Models\WAutomationSequenceStep;
use Illuminate\Database\Eloquent\Model;
use App\Services\API\WhatsAppSendingService;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WhatsAppSendingMessageService;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;



class WhatsAppSendingWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
    }


    public function sendScheduledWAPSenderMessages(Request $req)
    {
        $lockKey = 'sendScheduledWAPSenderMessages';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 90)) {
            die('Locked');
        }

        $WAPSenderService = resolve(WAPSenderService::class);
        $wapSendingMessageService = resolve(WhatsAppSendingMessageService::class);

        $clientId = $req->input('client_id') ?? null;
        $clients = resolve(ClientService::class)->findWithEnabledWAPSenderJob();
        if ($clientId) {
            $clients = $clients->filter(fn (Client $c) => $c->id == $clientId);
        }
        foreach ($clients as $client) {
            SystemHelper::doFlush();
            $this->printClientInfo($client);
            
            // Ej: si son las 15 -> agarro sendDate desde 14 a 15
            $dateEnd = new DateTime('now');
            $dateStart = new DateTime('now -3 hour');
            // $dateEnd = new DateTime('2025-07-23 18:46:53');
            // $dateStart = new DateTime('2025-07-23 15:46:53');
            try {
                // Para evitar solapamientos si programan la misma fecha/hora, traigo de a pocos.
                $wapSendingMessages = $wapSendingMessageService->findWAPSenderScheduledToSendBetweenSendDates(
                    $client, $dateStart, $dateEnd, ['limit' => 30]
                );
                $enabledWapSendingMessages = $wapSendingMessages->filter(function ($wapMsg) {
                    $canDispatch = !$wapMsg->error_message && !$wapMsg->dispatched_date && $wapMsg->success === null;
                    return $canDispatch;
                });
                if ($enabledWapSendingMessages->isEmpty()) {
                    continue;
                }

                $WAPSenderService->dispatchMultipleMessages($enabledWapSendingMessages);
                $this->printWapSendingMessagesDispatchedInfo($enabledWapSendingMessages);
            } catch (Throwable $e) {
                dump($e);
                report($e);
            }

            resolve(LockHelper::class)->getLockByName($lockKey, 90);
            $this->printSeparator();
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function sendScheduledWAPIMessages(Request $request)
    {
        $lockKey = 'sendScheduledWAPIMessages';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 90)) {
            die('Locked');
        }

        $WAPIService = resolve(WAPIService::class);
        $wapSendingService = resolve(WhatsAppSendingService::class);
        $wapSendingMessageService = resolve(WhatsAppSendingMessageService::class);

        $clients = resolve(ClientService::class)->findWithEnabledWAPI();
        foreach ($clients as $client) {
            SystemHelper::doFlush();
            $this->printClientInfo($client);
            
            // Ej: si son las 15 -> agarro sendDate desde 14 a 15
            $dateEnd = new DateTime('now');
            $dateStart = new DateTime('now -1 hour');
            // $dateEnd = new DateTime('2024-04-15 11:24:00');
            // $dateStart = new DateTime('2024-04-15 10:24:00');
            try {
                // Para evitar solapamientos si programan la misma fecha/hora, traigo siempre de a UN ENVÍO.
                $wapSendings = $wapSendingService->findWAPIScheduledEnabledToSendBetweenSendDatesByClient(
                    $client, $dateStart, $dateEnd, ['limit' => 1]
                );
                if ($wapSendings->isEmpty()) {
                    continue;
                }
                
                $wapSending = $wapSendings->first();
                $enabledWapSendingMessages = $wapSending->whatsAppSendingMessages
                    ->filter(function ($wapSendingMsg) use ($wapSendingMessageService) {
                        try {
                            // Intenta validar el mensaje. Si pasa la validación, lo consideramos elegible.
                            $wapSendingMessageService->validateIsEnabledToDispatch($wapSendingMsg);
                            return true;
                        } catch (Exception $e) {
                            // Si el mensaje no es elegible, lo marco como success=0, le seteo el error, y lo quito
                            $wapSendingMessageService->markAsFailed($wapSendingMsg, $e->getMessage());
                            return false;
                        }
                    })
                    ->values();
                ;
                $enabledWapSendingMessages = $enabledWapSendingMessages->filter(function ($wapMsg) {
                    $canDispatch = !$wapMsg->error_message && !$wapMsg->dispatched_date && $wapMsg->success === null;
                    return $canDispatch;
                });
                // $disabledWapSendingMessages = $wapSending->whatsAppSendingMessages->diff($enabledWapSendingMessages);
                if ($enabledWapSendingMessages->isEmpty()) {
                    continue;
                }

                // Dejo solamente los mensajes habilitados para ser despachados.
                $wapSending->whatsAppSendingMessages = $enabledWapSendingMessages;

                $WAPIService->dispatchWhatsAppSendingMessages($wapSending);
                $this->printWapSendingDispatchedInfo($wapSending);
            } catch (Throwable $e) {
                dump($e);
                report($e);
            }

            resolve(LockHelper::class)->getLockByName($lockKey, 90);
            $this->printSeparator();
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function sendScheduledWhatsAppMetaAPIMessages(Request $req)
    {
        $lockKey = 'sendScheduledWhatsAppMetaAPIMessages';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 90)) {
            die('Locked');
        }

        $whatsAppMetaAPIService = resolve(WhatsAppMetaAPIService::class);
        $wapSendingMessageService = resolve(WhatsAppSendingMessageService::class);

        $clientId = $req->input('client_id') ?? null;
        $clients = resolve(ClientService::class)->findWithEnabledWhatsAppMetaAPI();
        if ($clientId) {
            $clients = $clients->filter(fn (Client $c) => $c->id == $clientId);
        }
        foreach ($clients as $client) {
            SystemHelper::doFlush();
            $this->printClientInfo($client);
            
            $dateEnd = new DateTime('now');
            $dateStart = new DateTime('now -3 hour');
            // $dateEnd = new DateTime('2025-07-23 18:46:53');
            // $dateStart = new DateTime('2025-07-23 15:46:53');
            try {
                // Para evitar solapamientos si programan la misma fecha/hora, traigo de a pocos.
                $wapSendingMessages = $wapSendingMessageService->findWhatsAppMetaAPIScheduledToSendBetweenSendDates(
                    $client, $dateStart, $dateEnd, ['limit' => 30]
                );
                // Reduntante, pero por las dudas
                $enabledWapSendingMessages = $wapSendingMessages->filter(function ($wapMsg) {
                    $canDispatch = !$wapMsg->sent_date &&
                        !$wapMsg->error_message &&
                        $wapMsg->success === null &&
                        !$wapMsg->cancelled_date &&
                        !$wapMsg->dispatched_date
                    ;
                    return $canDispatch;
                });
                if ($enabledWapSendingMessages->isEmpty()) {
                    continue;
                }

                $whatsAppMetaAPIService->dispatchMultipleMessages($enabledWapSendingMessages);
                $this->printWapSendingMessagesDispatchedInfo($enabledWapSendingMessages);
            } catch (Throwable $e) {
                dump($e);
                report($e);
            }

            resolve(LockHelper::class)->getLockByName($lockKey, 90);
            $this->printSeparator();
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    public function markStuckedSendingsAsFailed(Request $request)
    {
        $lockKey = 'markStuckedSendingsAsFailed';
        if (!resolve(LockHelper::class)->getLockByName($lockKey, 90)) {
            die('Locked');
        }

        $wapSendingService = resolve(WhatsAppSendingService::class);
        $wapSendingMessageService = resolve(WhatsAppSendingMessageService::class);
        
        $clients = resolve(ClientService::class)->findWithEnabledWAPIOrWapSender();
        foreach ($clients as $client) {
            SystemHelper::doFlush();

            $stuckedWapMessages = $wapSendingMessageService->findStuckedByClient($client);
            // Tomo SOLAMENTE los de Wap Sender.
            $stuckedWapMessages = $stuckedWapMessages->where('type', WhatsAppSendingMessage::WAP_SENDER_TYPE);
            if ($stuckedWapMessages->isEmpty()) {
                continue;
            }
            
            $this->printClientInfo($client);
            foreach ($stuckedWapMessages as $stuckedWapMsg) {
                $wapSendingMessageService->markAsFailed($stuckedWapMsg, '[SYSTEM] message_stucked');
                $this->printWapSendingMessageMarkedAsFailedInfo($stuckedWapMsg);
            }

            $wapSendingIds = $stuckedWapMessages->pluck('whatsapp_sending_id')->unique()->values()->toArray();
            $wapSendings = $wapSendingService->findByClientAndIds($client, $wapSendingIds);
            foreach ($wapSendings as $wapSending) {
                $wapSending = $wapSendingService->markAsFailedIfAllMessagesFailed(
                    $wapSending, '[SYSTEM] sending_stucked'
                );

                if ($wapSending->failed_date) {
                    $this->printWapSendingMarkedAsFailedInfo($wapSending);
                }
            }

            $this->printSeparator();
        }
        resolve(LockHelper::class)->releaseLockByName($lockKey);
    }


    private function printSeparator(): void
    {
        echo "<br/><hr/><br/>";
    }


    private function printClientInfo(Client $client): void
    {
        echo "<h3>- Client ID {$client->id}: {$client->name} </h3> <br/>";
    }


    private function printWapSendingMarkedAsFailedInfo(WhatsAppSending $wapSending): void
    {
        echo "- Wap Sending ID {$wapSending->id} marked as failed <br/>";
    }


    private function printWapSendingMessageMarkedAsFailedInfo(WhatsAppSendingMessage $wapSendingMessage): void
    {
        echo "- Wap Sending Message ID {$wapSendingMessage->id} marked as failed <br/>";
    }


    private function printWapSendingDispatchedInfo(WhatsAppSending $wapSending): void
    {
        echo "- Wap Sending ID {$wapSending->id} dispatched <br/>";
        foreach ($wapSending->whatsAppSendingMessages as $wapSendingMessage) {
            echo "&nbsp;&nbsp;&nbsp; - Wap Sending Message ID {$wapSendingMessage->id} dispatched <br/>";
        }
    }


    private function printWapSendingMessagesDispatchedInfo(Collection $wapSendingMsgs): void
    {
        foreach ($wapSendingMsgs as $wapSendingMessage) {
            echo "&nbsp;&nbsp;&nbsp; - Wap Sending Message ID {$wapSendingMessage->id} dispatched <br/>";
        }
    }

}
