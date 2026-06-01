<?php

namespace App\Jobs\WhatsAppEvents;

use Exception;
use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\API\WapBot\WapBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\WapBot\WapBotConversationService;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;


/**
 * queue: ENV_wap_bot_queue
 */
class WapBotSendFollowUpMessageJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable;

    protected $logUuid = null;

    /**
     * Margen de tolerancia para la validación de tiempo (minutos).
     * Permite que el job se ejecute hasta X minutos después del límite teórico.
     */
    private const TIME_TOLERANCE_MINUTES = 30;


    public function __construct(
        public readonly int $wapBotId,
        public readonly string $wapBotConversationId,
    ) {
    }


    public function handle()
    {
        $this->logUuid = Str::orderedUuid();

        $this->logInfo('Starting WapBotSendFollowUpMessageJob');
        $this->logInfo("wapBotId: {$this->wapBotId}");
        $this->logInfo("wapBotConversationId: {$this->wapBotConversationId}");

        // Validar WapBot
        $wapBot = resolve(WapBotService::class)->find($this->wapBotId);
        if (!$wapBot) {
            $this->logInfo('WapBot not found. RETURNING.');
            return true;
        }
        if (!$wapBot->enabled) {
            $this->logInfo('WapBot is not enabled. RETURNING.');
            return true;
        }

        // Validar configuración de follow-up
        if (!$wapBot->followup_1_message || !$wapBot->followup_1_delay_minutes) {
            $this->logInfo('WapBot follow-up not configured (missing message or delay). RETURNING.');
            return true;
        }

        $this->logInfo("clientId: {$wapBot->client_id}");
        $client = $wapBot->client;
        if (!$client?->enabled) {
            $this->logInfo('Client is not enabled. RETURNING.');
            return true;
        }

        // Validar conversación
        $wapBotConversationService = resolve(WapBotConversationService::class);
        $wapBotConversation = $wapBotConversationService->find($this->wapBotConversationId);
        if (!$wapBotConversation) {
            $this->logInfo('WapBotConversation not found. RETURNING.');
            return true;
        }
        if ($wapBotConversation->isEnded) {
            $this->logInfo('WapBotConversation was ended. RETURNING.');
            return true;
        }
        if ($wapBotConversation->followUpMessage1) {
            $this->logInfo('WapBotConversation follow up message was already sent. RETURNING.');
            return true;
        }

        // Validación redundante de tiempo: verificar que la conversación sigue en ventana válida
        if (!$this->isWithinValidTimeWindow($wapBotConversation, $wapBot->followup_1_delay_minutes)) {
            $this->logInfo('Conversation is no longer within valid time window for follow-up. RETURNING.');
            return true;
        }

        // Obtener conexión de WhatsApp
        $whatsAppConnection = resolve(WhatsAppMetaAPIService::class)->findActiveConnection(
            $wapBot->client, $wapBot->meta_phone_number_id
        );
        if (!$whatsAppConnection) {
            $this->logInfo('WhatsAppMetaAPI active connection not found. RETURNING.');
            return true;
        }
        $this->logInfo("whatsAppConnectionId: {$whatsAppConnection->id}");

        $customerPhoneNumber = $wapBotConversation->customerPhoneNumber;
        $this->logInfo("customerPhoneNumber: {$customerPhoneNumber}");

        // Enviar mensaje de follow-up
        $whatsAppHelper = resolve(WhatsAppMetaAPIHelper::class);

        try {
            $sendResult = $whatsAppHelper->sendTextMessage(
                $whatsAppConnection, $customerPhoneNumber, $wapBot->followup_1_message
            );
            $sentMessageMetaId = $sendResult['messages'][0]['id'] ?? null;

            // Persistir resultado exitoso
            $wapBotConversation->followUpMessage1 = [
                'metaError' => null,
                'metaStatus' => 'accepted',
                'sentAt' => now()->toIso8601String(),
                'metaMessageId' => $sentMessageMetaId,
            ];
            $wapBotConversationService->save($wapBotConversation);

            $this->logInfo("FOLLOW UP MESSAGE SENT | sentMessageMetaId: {$sentMessageMetaId}");
        } catch (Throwable $e) {
            $this->logInfo("Error sending follow-up message: {$e->getMessage()}");

            // Persistir error
            $wapBotConversation->followUpMessage1 = [
                'sentAt' => now()->toIso8601String(),
                'metaMessageId' => null,
                'metaStatus' => 'error',
                'metaError' => $e->getMessage(),
            ];
            $wapBotConversationService->save($wapBotConversation);
            throw $e;
        }

        return true;
    }


    /**
     * Verifica que la conversación todavía esté dentro de la ventana de tiempo válida
     * para enviar el follow-up. Esto es una validación redundante por si el job
     * se ejecuta con mucho delay después de ser despachado.
     */
    protected function isWithinValidTimeWindow($wapBotConversation, int $delayMinutes): bool
    {
        $lastActivityAt = $wapBotConversation->lastActivityAt;
        if (!$lastActivityAt) {
            return false;
        }

        // El follow-up debería enviarse después de $delayMinutes desde la última actividad
        // Agregamos un margen de tolerancia para jobs que se ejecutan con delay
        $minutesSinceLastActivity = abs(now()->diffInMinutes($lastActivityAt));

        // Debe haber pasado al menos $delayMinutes (ya pasó el tiempo de espera)
        if ($minutesSinceLastActivity < $delayMinutes) {
            $this->logInfo(
                "Too early: {$minutesSinceLastActivity} min since last activity, delay is {$delayMinutes} min."
            );
            return false;
        }

        // No debe haber pasado demasiado tiempo (ventana de 24hs de Meta menos margen de seguridad)
        // Meta permite enviar mensajes hasta 24hs después del último mensaje del usuario
        $maxMinutes = 24 * 60 - self::TIME_TOLERANCE_MINUTES; // ~23.5 horas
        if ($minutesSinceLastActivity > $maxMinutes) {
            $this->logInfo("Too late: {$minutesSinceLastActivity} min since last activity, max is {$maxMinutes} min.");
            return false;
        }

        return true;
    }


    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
        Log::channel('WapBotSendFollowUpMessageJobErrors')->error((string) $e);
    }


    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }


    protected function getInfoLog()
    {
        return Log::channel('WapBotSendFollowUpMessageJobInfo');
    }

}
