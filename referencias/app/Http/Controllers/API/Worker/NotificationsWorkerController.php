<?php

namespace App\Http\Controllers\API\Worker;

use Exception;
use Carbon\Carbon;
use App\Models\Lead;
use App\Helpers\LockHelper;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Helpers\SystemHelper;
use App\Services\API\EmailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\API\ClientService;
use App\Models\WhatsAppMetaAPIConnection;
use App\Services\API\WhatsAppSendingService;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WhatsAppSendingMessageService;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\Services\API\Notifications\NotificationService;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;


class NotificationsWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(2000);
    }


    public function deleteNotificationsIfUserEmailWasEnabled(Request $req)
    {
        $lockHelper = resolve(LockHelper::class);
        $lockKeyName = 'deleteNotificationsIfUserEmailWasEnabled';
        if (!$lockHelper->getLockByName($lockKeyName, 30)) {
            die('Locked');
        }

        $dateNow = Carbon::now();
        $emailService = resolve(EmailService::class);
        $clientService = resolve(ClientService::class);
        $notificationService = resolve(NotificationService::class);

        $clients = $clientService->findAllEnabled();
        foreach ($clients as $client) {
            foreach ($client->users as $user) {
                SystemHelper::doFlush();
                $lockHelper->getLockByName($lockKeyName, 30);

                $notifications = $notificationService->findEmailSendingNotEnabledByUser($user);
                if ($notifications->isEmpty()) {
                    continue;
                }

                $deletedNotifications = collect([]);
                if (!$user->enabled) {
                    $deletedNotifications = $notificationService->deleteNotifications($notifications);
                    echo "- Client <b>{$client->name}</b>. User <b>{$user->email}</b> NOT ENABLED. ";
                    echo "Cleared {$deletedNotifications->count()} notifications. \n<br>\n<br>";
                    continue;
                }

                $userLastEmail = $emailService->findLastOneSentByUser($user);
                if (!$userLastEmail) {
                    continue;
                }

                $sentEmailDate = $userLastEmail->sent_date;
                $lastNotificationDate = $notifications->sortBy('created_at')->last()->created_at;
                if ($sentEmailDate > $lastNotificationDate) {
                    $deletedNotifications = $notificationService->deleteNotifications($notifications);
                    echo "- Client <b>{$client->name}</b>. User <b>{$user->email}</b>. ";
                    echo "Last notification date: <b>{$lastNotificationDate->format('Y-m-d H:i')}</b>. ";
                    echo "Last sent email date: <b>{$sentEmailDate->format('Y-m-d H:i')}</b>. ";
                    echo "Cleared {$deletedNotifications->count()} notifications. \n<br>\n<br>";
                    continue;
                }

                foreach ($notifications as $notification) {
                    $notificationDate = $notification->created_at;
                    $daysDiff = $notificationDate->diffInDays($dateNow);
                    if ($daysDiff < 10) {
                        continue;
                    }
                    $deletedNotification = $notificationService->delete($notification);
                    echo "- Client <b>{$client->name}</b>. User <b>{$user->email}</b>. ";
                    echo "Notification date: <b>{$notificationDate->format('Y-m-d')}</b> has 10 days or more. ";
                    echo "Cleared. \n<br>\n<br>";
                }
            }
        }
    }


    public function deleteNotificationsIfWAPIUserWasSynced(Request $req)
    {
        $lockHelper = resolve(LockHelper::class);
        $lockKeyName = 'deleteNotificationsIfWAPIUserWasSynced';
        if (!$lockHelper->getLockByName($lockKeyName, 30)) {
            die('Locked');
        }

        $dateNow = Carbon::now();
        $clientService = resolve(ClientService::class);
        $notificationService = resolve(NotificationService::class);
        $wapSendingMsgService = resolve(WhatsAppSendingMessageService::class);

        $clients = $clientService->findWithEnabledWAPI();
        foreach ($clients as $client) {
            foreach ($client->users as $user) {
                SystemHelper::doFlush();
                $lockHelper->getLockByName($lockKeyName, 30);

                $notifications = $notificationService->findWAPINotSyncedByUser($user);
                if ($notifications->isEmpty()) {
                    continue;
                }

                $deletedNotifications = collect([]);
                if (!$user->enabled) {
                    $deletedNotifications = $notificationService->deleteNotifications($notifications);
                    echo "- Client <b>{$client->name}</b>. User <b>{$user->email}</b> NOT ENABLED. ";
                    echo "Cleared {$deletedNotifications->count()} notifications. \n<br>\n<br>";
                    continue;
                }

                $userLastWAPISentMessage = $wapSendingMsgService->findLastOneSentWAPIByUser($user);
                if (!$userLastWAPISentMessage) {
                    continue;
                }

                $sentWapDate = $userLastWAPISentMessage->sent_date;
                $lastNotificationDate = $notifications->sortBy('created_at')->last()->created_at;
                if ($sentWapDate > $lastNotificationDate) {
                    $deletedNotifications = $notificationService->deleteNotifications($notifications);
                    echo "- Client <b>{$client->name}</b>. User <b>{$user->email}</b>. ";
                    echo "Last notification date: <b>{$lastNotificationDate->format('Y-m-d H:i')}</b>. ";
                    echo "Last sent WAPI msg date: <b>{$sentWapDate->format('Y-m-d H:i')}</b>. ";
                    echo "Cleared {$deletedNotifications->count()} notifications. \n<br>\n<br>";
                    continue;
                }

                foreach ($notifications as $notification) {
                    $notificationDate = $notification->created_at;
                    $daysDiff = $notificationDate->diffInDays($dateNow);
                    if ($daysDiff < 10) {
                        continue;
                    }
                    $deletedNotification = $notificationService->delete($notification);
                    echo "- Client <b>{$client->name}</b>. User <b>{$user->email}</b>. ";
                    echo "Notification date: <b>{$notificationDate->format('Y-m-d')}</b> has 10 days or more. ";
                    echo "Cleared. \n<br>\n<br>";
                }
            }
        }
    }


    /**
     * Recorre cada cliente con WhatsApp Meta API habilitado, evalúa el estado
     * de cada conexión contra Meta y gestiona la Notification del user:
     *  - si hay problema y no hay notif abierta → crea notif + email a soporte
     *  - si hay problema y ya hay notif → bump de updated_at (no reenvía email)
     *  - si NO hay problema pero hay notif abierta → la elimina (deleted_by_system=true)
     */
    public function sendNotSyncedWhatsAppMetaAPIConnectionsEmail(Request $req)
    {
        $lockHelper = resolve(LockHelper::class);
        $lockKeyName = 'sendNotSyncedWhatsAppMetaAPIConnectionsEmail';
        if (!$lockHelper->getLockByName($lockKeyName, 30)) {
            die('Locked');
        }

        // send_email=false por default: permite correr el worker en modo "dry" para debug
        // sin notificar a soporte. Las Notifications se crean igual.
        $sendEmail = $req->boolean('send_email', false);

        // Filtros opcionales:
        //  - client_id=X        → revisa solo el cliente X
        //  - min_client_id=X    → revisa clientes con id >= X
        $onlyClientId = $req->query('client_id');
        $minClientId = $req->query('min_client_id');

        $clientService = resolve(ClientService::class);
        $notificationService = resolve(NotificationService::class);
        $whatsAppMetaAPIHelper = resolve(WhatsAppMetaAPIHelper::class);
        $whatsAppMetaAPIService = resolve(WhatsAppMetaAPIService::class);

        // Cartel inicial con el modo de ejecución.
        if ($sendEmail) {
            echo "<div style='background:#d4edda; color:#155724; padding:10px; ";
            echo "border-left:4px solid #28a745; margin-bottom:10px;'>";
            echo "<b>send_email=true</b> — Se enviarán emails a soporte cuando se detecten nuevos problemas.";
            echo "</div>\n";
        } else {
            echo "<div style='background:#fff3cd; color:#856404; padding:10px; ";
            echo "border-left:4px solid #ffc107; margin-bottom:10px;'>";
            echo "<b>send_email=false</b> — Modo dry-run: NO se enviarán emails (las Notifications se crean igual).";
            echo "</div>\n";
        }
        if ($onlyClientId) {
            echo "<div style='background:#e2e3e5; color:#383d41; padding:6px 10px; ";
            echo "margin-bottom:10px;'>Filtro: solo client_id=<b>{$onlyClientId}</b></div>\n";
        } elseif ($minClientId) {
            echo "<div style='background:#e2e3e5; color:#383d41; padding:6px 10px; ";
            echo "margin-bottom:10px;'>Filtro: clientes con id &gt;= <b>{$minClientId}</b></div>\n";
        }

        // Colores por estado para facilitar lectura al correrlo en navegador.
        $colorIncomplete = '#95a5a6'; // gris
        $colorPersistent = '#e67e22'; // naranja (problema persistente)
        $colorNewError   = '#c0392b'; // rojo (nuevo problema)
        $colorRestored   = '#27ae60'; // verde (restablecida)
        $colorOk         = '#7f8c8d'; // gris tenue (sin cambios)

        $clients = $clientService->findWithEnabledWhatsAppMetaAPI()->sortBy('id');
        if ($onlyClientId) {
            $clients = $clients->where('id', (int) $onlyClientId);
        } elseif ($minClientId) {
            $clients = $clients->where('id', '>=', (int) $minClientId);
        }

        foreach ($clients as $client) {
            echo "<hr style='border:0; border-top:2px solid #2c3e50; margin:20px 0 8px 0;'>";
            echo "<h3 style='margin:6px 0; color:#2c3e50;'>";
            echo "Cliente: {$client->name} (ID: {$client->id})</h3>\n";

            $whatsAppMetaAPIConnections = $whatsAppMetaAPIService->findConnectionsByClient($client);
            foreach ($whatsAppMetaAPIConnections as $whatsAppMetaAPIConnection) {
                SystemHelper::doFlush();
                $lockHelper->getLockByName($lockKeyName, 30);

                // Conexiones sin user, waba_id, phone_number_id o access_token están a medio configurar
                // (onboarding incompleto). No son "problemas" reportables, se ignoran.
                $isIncomplete = !$whatsAppMetaAPIConnection->user_id
                    || !$whatsAppMetaAPIConnection->waba_id
                    || !$whatsAppMetaAPIConnection->phone_number_id
                    || !$whatsAppMetaAPIConnection->access_token
                ;
                if ($isIncomplete) {
                    echo "<div style='color:{$colorIncomplete}; padding:2px 0;'>";
                    echo "- Conn ID: {$whatsAppMetaAPIConnection->id} ";
                    echo "está incompleta (onboarding sin finalizar). Se ignora.";
                    echo "</div>\n";
                    continue;
                }

                $user = $whatsAppMetaAPIConnection->user;
                $userLabel = "User <b>{$user->email} (ID: {$user->id})</b>";
                $errorDescription = $this->detectWhatsAppMetaAPIConnectionError(
                    $whatsAppMetaAPIConnection, $whatsAppMetaAPIHelper
                );
                $existingNotification = $notificationService->findOneByTypeAndUser(
                    Notification::TYPE_USER_WHATSAPP_META_API_NOT_SYNCED, $user
                );

                if ($errorDescription) {
                    if ($existingNotification) {
                        $existingNotification->touch();
                        echo "<div style='color:{$colorPersistent}; padding:2px 0;'>";
                        echo "- {$userLabel}. Conn ID: {$whatsAppMetaAPIConnection->id} sigue con problema: ";
                        echo "<b>{$errorDescription}</b>. Notificación actualizada.";
                        echo "</div>\n";
                        continue;
                    }
                    $newCreatedNotification = $notificationService->storeWhatsAppMetaAPINotSyncedError(
                        $whatsAppMetaAPIConnection
                    );
                    $emailStatus = 'email OMITIDO (send_email=false)';
                    if ($sendEmail) {
                        $notificationService->sendWhatsAppMetaAPINotSyncedEmail(
                            $newCreatedNotification, $whatsAppMetaAPIConnection, $errorDescription
                        );
                        $emailStatus = 'email enviado a soporte';
                    }
                    echo "<div style='color:{$colorNewError}; padding:2px 0;'>";
                    echo "- {$userLabel}. Nuevo problema en conn ID: {$whatsAppMetaAPIConnection->id}: ";
                    echo "<b>{$errorDescription}</b>. Notificación creada + {$emailStatus}.";
                    echo "</div>\n";
                    continue;
                }

                // Conexión sana.
                if ($existingNotification) {
                    $notificationService->delete($existingNotification);
                    echo "<div style='color:{$colorRestored}; padding:2px 0;'>";
                    echo "- {$userLabel}. Conn ID: {$whatsAppMetaAPIConnection->id} restablecida. ";
                    echo "Notificación eliminada.";
                    echo "</div>\n";
                    continue;
                }
                echo "<div style='color:{$colorOk}; padding:2px 0;'>";
                echo "- {$userLabel}. Conn ID: {$whatsAppMetaAPIConnection->id} OK, sin cambios.";
                echo "</div>\n";
            }
        }
    }


    /**
     * Evalúa los 4 criterios de "conexión con problema" en orden y devuelve
     * el primer error detectado, o null si la conexión está OK.
     */
    private function detectWhatsAppMetaAPIConnectionError(
        WhatsAppMetaAPIConnection $connection,
        WhatsAppMetaAPIHelper $whatsAppMetaAPIHelper
    ): ?string {
        try {
            $phoneInfo = $whatsAppMetaAPIHelper->getPhoneNumberInfoById(
                $connection->phone_number_id, $connection->access_token
            );
        } catch (Exception $e) {
            return 'Error consultando phone number en Meta: ' . Str::limit($e->getMessage(), 400);
        }

        $status = $phoneInfo['status'] ?? null;
        if ($status !== 'CONNECTED') {
            return 'Phone status inesperado: ' . ($status ?? 'null');
        }

        // Nos alcanza con que haya alguna URL de webhook configurada; la URL concreta
        // puede variar entre entornos (Clienty, notificaciones2, futuros forwarders).
        $webhookApplication = $phoneInfo['webhook_configuration']['application'] ?? null;
        if (empty($webhookApplication)) {
            return 'Webhook no configurado en Meta (webhook_configuration.application vacío)';
        }

        try {
            $isSubscribed = $whatsAppMetaAPIHelper->isWabaSubscribedToWebhooks(
                $connection->waba_id, $connection->access_token
            );
        } catch (Exception $e) {
            return 'Error consultando suscripciones de webhooks: ' . Str::limit($e->getMessage(), 400);
        }
        if (!$isSubscribed) {
            return 'WABA no suscrita a nuestros webhooks';
        }
        return null;
    }

}
