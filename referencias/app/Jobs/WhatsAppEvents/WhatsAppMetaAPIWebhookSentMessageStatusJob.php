<?php

namespace App\Jobs\WhatsAppEvents;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\API\EventsLogService;
use App\Models\WhatsAppSendingMessage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\WhatsAppSendingMessageService;
use App\Models\MongoDB\WhatsAppMetaAPI\WhatsAppConversationMessage;
use App\Helpers\WhatsAppMetaAPI\WhatsAppConversationRealTimeHelper;
use App\Services\API\WhatsAppMetaAPI\WhatsAppConversationMessageService;


/**
 * queue: ENV_whatsapp_meta_api_webhook_queue
 *
 * @todo FF
 * El estado en Mongo/UI puede retroceder con webhooks viejos.
 * Revisar método updateAndBroadcastConversationMessageStatus y metaWebhookTs (hoy la conversation no guarda ese ts)
 *
 * @todo FF
 * Hay una race condition que puede perder el status para siempre si el webhook llega antes de que exista el
 * Si no encuentra el WhatsAppConversationMessage, el job hace return y no reintenta.
 * WhatsAppMetaAPISentConversationMessageStoreJob podría correr después de este job.
 */
class WhatsAppMetaAPIWebhookSentMessageStatusJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable;


    public function __construct(
        public readonly array $metaWebhookPayload,
    ) {
    }


    public function handle()
    {
        $payload = $this->metaWebhookPayload;
        $entries = $payload['entry'] ?? [];
        $object = $payload['object'] ?? null;

        $this->getInfoLog()->info('-----------------------------------------------'  . PHP_EOL);
        $this->getInfoLog()->info('WhatsAppMetaAPIWebhookSentMessageStatusJob INICIADO');

        if ($object != 'whatsapp_business_account' || !$entries) {
            $this->getInfoLog()->info('Webhook ignorado: object/entry inválidos', $payload);
            return;
        }

        $wapMessageService = resolve(WhatsAppSendingMessageService::class);

        foreach ($entries as $entryIndex => $entry) {
            $changes = $entry['changes'] ?? [];
            if (!$changes) {
                $this->getInfoLog()->info('Entry sin changes, se ignora');
                continue;
            }

            foreach ($changes as $change) {
                $field = $change['field'] ?? null;
                $product = $change['value']['messaging_product'] ?? null;

                if ($field != 'messages' || $product != 'whatsapp') {
                    $this->getInfoLog()->info('Change no es de WhatsApp/messages, se ignora');
                    continue;
                }

                $statuses = $change['value']['statuses'] ?? [];
                // Si no hay statuses, probablemente sea un inbound (messages[]) u otro evento
                if (!$statuses) {
                    $this->getInfoLog()->info('Change sin statuses, se ignora');
                    continue;
                }

                foreach ($statuses as $statusData) {
                    $metaMessageId = $statusData['id'] ?? null; // ej: "wamid.HB..."
                    $metaErrorsArr = $statusData['errors'] ?? null;
                    $metaWebhookTs = (int) ($statusData['timestamp'] ?? 0);
                    $metaStatus = strtolower($statusData['status'] ?? ''); // sent|delivered|read|failed|deleted
                    $customerPhoneNumber = $statusData['recipient_id'] ?? null;
                    $metaConnectedPhoneNumberId = $change['value']['metadata']['phone_number_id'] ?? null;
                    
                    if (!$metaMessageId || !$metaStatus) {
                        $this->getInfoLog()->info('Status inválido: faltan campos');
                        continue;
                    }

                    $this->getInfoLog()->info(
                        'Webhook válido', ['metaId' => $metaMessageId, 'metaStatus' => $metaStatus]
                    );

                    // Va acá, para que no dependa de si existe o no $wapMessage
                    // Actualizar metaStatus y metaError en el ConversationMessage de MongoDB
                    if ($metaConnectedPhoneNumberId && $customerPhoneNumber) {
                        $this->updateAndBroadcastConversationMessageStatus(
                            metaStatus: $metaStatus,
                            metaWebhookTs: $metaWebhookTs,
                            metaMessageId: $metaMessageId,
                            metaErrorsArr: $metaErrorsArr,
                            customerPhoneNumber: $customerPhoneNumber,
                            metaConnectedPhoneNumberId: $metaConnectedPhoneNumberId,
                        );
                    }

                    $wapMessage = $wapMessageService->findOneByMetaId($metaMessageId);
                    if (!$wapMessage) {
                        // No encontrado: log y seguir (no fallamos el job)
                        $this->getInfoLog()->info(
                            'Mensaje no encontrado por meta_id', ['meta_msg_id' => $metaMessageId]
                        );
                        continue;
                    }
                    $this->getInfoLog()->info(
                        'WhatsAppSendingMessage encontrado', $wapMessage->only(['id', 'client_id', 'user_id'])
                    );

                    $wapMsgWebhookTs = $wapMessage->meta_webhook_ts ?? 0;
                    if ($metaWebhookTs && $wapMsgWebhookTs >= $metaWebhookTs) {
                        $this->getInfoLog()->info(
                            "Timestamp de evento ($metaWebhookTs) menor a timestamp de mensaje ($wapMsgWebhookTs)",
                            $wapMessage->only(['id', 'client_id', 'user_id'])
                        );
                        continue;
                    }

                    $wapMsgData = ['meta_status' => $metaStatus, 'meta_webhook_ts' => $metaWebhookTs];
                    if ($metaStatus == 'failed') {
                        // WhatsApp puede mandar errors[*] con code/title/description
                        if ($statusData['errors'] ?? null) {
                            $wapMsgData['success'] = false;
                            $wapMsgData['error_message'] = json_encode($statusData['errors']);
                        }
                    }
                    if (in_array($metaStatus, ['sent', 'delivered', 'read'])) {
                        $wapMsgData['success'] = true;
                        $wapMsgData['error_message'] = null;
                    }

                    // Actualizar WapMessage en DB
                    $wapMessage->fill($wapMsgData);
                    $wapMessage->saveOrFail();

                    $this->getInfoLog()->info(
                        'WhatsAppSendingMessage actualizado',
                        ['meta_msg_id' => $metaMessageId, 'meta_status' => $metaStatus]
                    );

                    // Si el mensaje falló, actualizar el EventLog correspondiente en MongoDB
                    if ($metaStatus == 'failed') {
                        $this->updateEventLogOnFailure($wapMessage);
                    }
                }
            }
        }
    }


    protected function updateEventLogOnFailure(WhatsAppSendingMessage $wapMessage): void
    {
        try {
            $eventsLogService = resolve(EventsLogService::class);
            $eventLogs = $eventsLogService->findWhatsAppSendingMessageSentLogs($wapMessage);

            if ($eventLogs->isEmpty()) {
                $this->getInfoLog()->info(
                    'EventLog no encontrado para mensaje fallido',
                    ['whatsapp_sending_message_id' => $wapMessage->id]
                );
                return;
            }

            $this->getInfoLog()->info(
                'EventLogs encontrados para actualizar',
                ['count' => $eventLogs->count(), 'whatsapp_sending_message_id' => $wapMessage->id]
            );

            foreach ($eventLogs as $eventLog) {
                $eventsLogService->updateWhatsAppSendingMessageSuccess($eventLog, false);
                $this->getInfoLog()->info(
                    'EventLog actualizado a failed',
                    ['event_log_id' => $eventLog->_id, 'whatsapp_sending_message_id' => $wapMessage->id]
                );
            }
        } catch (Throwable $e) {
            // Log el error pero no fallar el job por esto
            $this->getInfoLog()->info((string) $e);
        }
    }


    protected function updateAndBroadcastConversationMessageStatus(
        string $metaConnectedPhoneNumberId,
        string $customerPhoneNumber,
        string $metaMessageId,
        int $metaWebhookTs,
        string $metaStatus,
        ?array $metaErrorsArr
    ): void {
        try {
            $this->getInfoLog()->info('updateAndBroadcastConversationMessageStatus() START');
            $this->getInfoLog()->info("- metaConnectedPhoneNumberId: {$metaConnectedPhoneNumberId}");
            $this->getInfoLog()->info("- customerPhoneNumber: {$customerPhoneNumber}");
            $this->getInfoLog()->info("- metaMessageId: {$metaMessageId}");
            $this->getInfoLog()->info("- metaWebhookTs: {$metaWebhookTs}");
            $this->getInfoLog()->info("- metaStatus: {$metaStatus}");
            $this->getInfoLog()->info("- metaErrorsArr: " . json_encode($metaErrorsArr));

            $wapConversationMsg = resolve(WhatsAppConversationMessageService::class)->findOneByMetaMessageId(
                metaMessageId: $metaMessageId,
                customerPhoneNumber: $customerPhoneNumber,
                metaConnectedPhoneNumberId: $metaConnectedPhoneNumberId,
            );

            if (!$wapConversationMsg) {
                $this->getInfoLog()->info('wapConversationMsg not found');
                $this->getInfoLog()->info('updateAndBroadcastConversationMessageStatus() RETURN');
                return;
            }
            $this->getInfoLog()->info("- wapConversationMsgId: {$wapConversationMsg->id}");

            if ($wapConversationMsg->direction != WhatsAppConversationMessage::DIRECTION_OUTGOING) {
                $this->getInfoLog()->info('wapConversationMsg direction is not "outgoing"');
                $this->getInfoLog()->info('updateAndBroadcastConversationMessageStatus() RETURN');
                return;
            }
            if ($wapConversationMsg->source != WhatsAppConversationMessage::SOURCE_API_MESSAGE) {
                $this->getInfoLog()->info('wapConversationMsg source is not "meta_api"');
                $this->getInfoLog()->info('updateAndBroadcastConversationMessageStatus() RETURN');
                return;
            }

            $wapConversationMsg->metaStatus = $metaStatus;
            $wapConversationMsg->metaError = $metaErrorsArr;
            $wapConversationMsg->save();

            // Broadcast en tiempo real si hay viewers activos
            $clientId = (int) ($wapConversationMsg->metaRawPayload['clienty']['clientId'] ?? 0);
            if ($clientId) {
                try {
                    $this->getInfoLog()->info("- broadcastConversationMessageStatusUpdate()");
                    resolve(WhatsAppConversationRealTimeHelper::class)->broadcastConversationMessageStatusUpdate(
                        $wapConversationMsg, $clientId
                    );
                } catch (Throwable $e) {
                    report($e);
                    $this->getInfoLog()->info("broadcastConversationMessageStatusUpdate error: {$e}");
                }
            }

            $this->getInfoLog()->info('updateAndBroadcastConversationMessageStatus() FINISHED SUCCESSFULLY');
        } catch (Throwable $e) {
            report($e);
            $this->getInfoLog()->info('updateAndBroadcastConversationMessageStatus() Error: ' . (string) $e);
        }
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->info($e);
        $this->getErrorLog()->error($e);
        $this->getErrorLog()->error(PHP_EOL . PHP_EOL);
    }


    public function getInfoLog()
    {
        return Log::channel('WhatsAppMetaAPIWebhookSentMessageStatusJobInfo');
    }


    public function getErrorLog()
    {
        return Log::channel('WhatsAppMetaAPIWebhookSentMessageStatusJobErrors');
    }

}
