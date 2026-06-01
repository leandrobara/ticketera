<?php

namespace App\Jobs\WhatsAppEvents;

use Exception;
use Throwable;
use App\Models\WapBot;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Services\API\LeadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\WhatsAppSendingMessage;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\WhatsAppMetaAPIConnection;
use App\Services\API\WapBot\WapBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\MongoDB\WapBot\WapBotConversation;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\WhatsAppSendingMessageService;
use App\DTO\WapBot\WhatsAppMetaAPIWebhookPayloadDTO;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\Services\API\WapBot\WapBotConversationService;
use App\Services\API\WhatsAppMetaAPI\WhatsAppMetaAPIService;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;


/**
 * queue: ENV_wap_bot_queue
 */
class WapBotAnswerIncomingMessageJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable;

    protected $logUuid = null;


    public function __construct(
        public readonly array $metaWebhookPayload,
    ) {
    }


    public function handle()
    {
        $this->logUuid = Str::orderedUuid();
        $this->logInfo('Starting WapBotAnswerIncomingMessageJob');
        $this->logInfo(json_encode($this->metaWebhookPayload));

        $payloadDTO = new WhatsAppMetaAPIWebhookPayloadDTO($this->metaWebhookPayload);
        if (!$payloadDTO->isIncomingMessage()) {
            $this->logInfo('Payload is not an incoming message. RETURNING.');
            return true;
        }
        if (!$payloadDTO->isParsableMessage()) {
            $this->logInfo('Payload is not a parsable message. RETURNING.');
            return true;
        }

        $referralData = $payloadDTO->getReferralData();
        $customerPhoneNumber = $payloadDTO->getFromNumber();
        $receivedMetaMessageId = $payloadDTO->getMessageId();
        $connectedPhoneNumberId = $payloadDTO->getPhoneNumberId();
        $receivedMetaMessageTimestamp = $payloadDTO->getTimestamp();

        if (!$connectedPhoneNumberId || !$customerPhoneNumber) {
            $this->logInfo('Missing phone identifiers in payload. RETURNING.');
            return true;
        }

        $wapBotService = resolve(WapBotService::class);
        $wapBot = $wapBotService->findActiveByMetaPhoneNumberId($connectedPhoneNumberId);
        if (!$wapBot) {
            $this->logInfo('No active WapBot. RETURNING.');
            return true;
        }
        $this->logInfo("wapBotId: {$wapBot->id}");
        $this->logInfo("clientId: {$wapBot->client_id}");

        if (!$wapBot->client || !$wapBot->client->enabled) {
            $this->logInfo('Client missing or disabled. RETURNING.');
            return true;
        }

        $whatsAppMetaAPIService = resolve(WhatsAppMetaAPIService::class);
        $whatsAppConnection = $whatsAppMetaAPIService->findActiveConnection($wapBot->client, $connectedPhoneNumberId);
        if (!$whatsAppConnection) {
            $this->logInfo('No WhatsAppMetaAPIConnection. RETURNING.');
            return true;
        }
        $this->logInfo("whatsAppConnectionId: {$whatsAppConnection->id}");

        $incomingMessageText = (string) ($payloadDTO->getMessage() ?? '');
        if (trim(strtoupper($incomingMessageText)) === '/PROMPT') {
            $this->sendAndStoreMessage(
                wapBot: $wapBot,
                promptText: $wapBot->prompt,
                messageText: '<PROMPT ENVIADO>',
                whatsAppConnection: $whatsAppConnection,
                customerPhoneNumber: $customerPhoneNumber,
            );
            return true;
        }

        $conversationService = resolve(WapBotConversationService::class);
        $conversation = $conversationService->findLatestConversation(
            clientId: $wapBot->client_id,
            customerPhoneNumber: $customerPhoneNumber,
            botMetaPhoneNumberId: $connectedPhoneNumberId,
        );
        $this->logInfo("conversationId: {$conversation?->id}");

        if (trim(strtoupper($incomingMessageText)) === '/CLEAR') {
            $this->logInfo("Borrando historial con: {$customerPhoneNumber}");
            if ($conversation) {
                $conversationService->delete($conversation);
            }
            $this->sendAndStoreMessage(
                wapBot: $wapBot,
                wapBotConversation: $conversation,
                whatsAppConnection: $whatsAppConnection,
                customerPhoneNumber: $customerPhoneNumber,
                messageText: 'Listo, limpié la conversación. ¡Podés empezar de nuevo!',
            );
            return true;
        }

        if ($conversation && $conversation->wapBotId && $conversation->wapBotId !== $wapBot->id) {
            $wapBot = $wapBotService->find($conversation->wapBotId, ['withTrashed' => true]);
            if (!$wapBot) {
                $msg = "WapBotConversation referenced WapBot ID: {$conversation->wapBotId} not found. RETURNING.";
                $this->logInfo($msg);
                return true;
            }
        }

        // Verificar si es una conversación semilla permanente (nunca intervenir)
        if ($conversation && $conversation->isPermanentSeedConversation) {
            $this->logInfo('Conversation is permanent seed. RETURNING.');
            return true;
        }

        $chatHasBeenActiveLastDays = false;
        if ($conversation) {
            $chatIsEnded = $conversation->isEnded;
            $intervalDays = $wapBot->reactivation_interval_days;
            $chatHasBeenActiveLastDays = $conversation->hasBeenActiveWithinLastDays($intervalDays);
            if ($chatIsEnded && $chatHasBeenActiveLastDays) {
                $this->logInfo('Conversation marked as ended. RETURNING.');
                return true;
            }

            // Si el vendedor le escribió manualmente al customer dentro de la ventana de reactivación,
            // no intervenimos: el cliente está respondiéndole al vendedor
            $customerHasBeenMessaged = $conversation->customerHasBeenMessagedWithinLastDays($intervalDays);
            if ($chatIsEnded && $customerHasBeenMessaged) {
                $this->logInfo('Customer has been messaged within reactivation window. RETURNING.');
                return true;
            }

            $conversation = $conversationService->updateReceivedMetaMessageInfo(
                conversation: $conversation,
                lastMetaMessageId: $receivedMetaMessageId,
                lastMetaMessageTimestamp: $receivedMetaMessageTimestamp,
            );
        }

        if (!$conversation || !$chatHasBeenActiveLastDays) {
            $conversation = $conversationService->createBotConversation(
                wapBot: $wapBot,
                referralData: $referralData,
                connection: $whatsAppConnection,
                customerPhoneNumber: $customerPhoneNumber,
                lastMetaMessageId: $receivedMetaMessageId,
                lastMetaMessageTimestamp: $receivedMetaMessageTimestamp,
            );
        }

        $this->logInfo('Sending Wap typing indicator...');
        resolve(WhatsAppMetaAPIHelper::class)->sendTypingIndicator($whatsAppConnection, $receivedMetaMessageId);

        if ($payloadDTO->isAttachment()) {
            $attachmentTypeLegend = $payloadDTO->getAttachmentTypeLegend();
            $messageType = $payloadDTO->getMessageType() ?? 'attachment';
            $conversation = $conversationService->addUserMessageToOpenAIHistory(
                messageType: $messageType,
                conversation: $conversation,
                message: "{$messageType} recibido>",
                metaMessageId: $receivedMetaMessageId,
                context: ['media_type' => $messageType],
            );

            $receivedAttachmentText = "Recibí {$attachmentTypeLegend}. Por ahora no puedo procesarlo.";
            if ($wapBot->client->country_code == 'AR') {
                $receivedAttachmentText .= " ¿Podés responder con texto o elegir una opción?";
            } else {
                $receivedAttachmentText .= " Puedes responder con texto o elegir una opción?";
            }

            $sendResult = $this->sendAndStoreMessage(
                wapBot: $wapBot,
                wapBotConversation: $conversation,
                messageText: $receivedAttachmentText,
                whatsAppConnection: $whatsAppConnection,
                customerPhoneNumber: $customerPhoneNumber,
            );

            $assistantMessageId = $sendResult['messages'][0]['id'] ?? null;
            $conversation = $conversationService->addAssistantMessageToOpenAIHistory(
                conversation: $conversation,
                messageType: 'assistant_text',
                message: $receivedAttachmentText,
                metaMessageId: $assistantMessageId,
            );

            $conversationService->save($conversation);
            $this->logInfo("Message is attachment. Returned: '{$receivedAttachmentText}'");
            return true;
        }

        $conversation = $conversationService->addUserMessageToOpenAIHistory(
            conversation: $conversation,
            message: $incomingMessageText,
            metaMessageId: $receivedMetaMessageId,
            messageType: $payloadDTO->getMessageType() ?? 'text',
            context: ['button_id' => $payloadDTO->getButtonId(), 'button_title' => $payloadDTO->getButtonTitle()],
        );
        $conversationService->save($conversation);

        $openAIHistoryMessages = $this->buildOpenAIHistoryMessages($conversation->openAIHistory, $wapBot->prompt);
        $openAIResponseStr = $this->getOpenAIResponse($openAIHistoryMessages);
        $openAIResponse = $openAIResponseStr ? $this->parseOpenAIJsonResponse($openAIResponseStr) : null;
        if (!$openAIResponse) {
            $fallbackText = 'Hubo un error al procesar tu respuesta. ¿Puedes intentarlo de nuevo?';
            $sendResult = $this->sendAndStoreMessage(
                wapBot: $wapBot,
                messageText: $fallbackText,
                wapBotConversation: $conversation,
                whatsAppConnection: $whatsAppConnection,
                customerPhoneNumber: $customerPhoneNumber,
            );
            $assistantMessageId = $sendResult['messages'][0]['id'] ?? null;
            $conversation = $conversationService->addAssistantMessageToOpenAIHistory(
                message: $fallbackText,
                conversation: $conversation,
                messageType: 'assistant_text',
                metaMessageId: $assistantMessageId,
            );

            $conversationService->save($conversation);
            $this->logInfo("OpenAI error. Returned: '{$fallbackText}'");
            return true;
        }

        $leadInfo = $openAIResponse['leadInfo'] ?? [];
        $nextMessageInfo = $openAIResponse['nextMessage'];
        $sendLeadToClienty = (bool) ($openAIResponse['sendLeadToClienty'] ?? false);
        
        // To avoid multiple messages (kind of race condition)
        if ($conversationService->hasReceivedNewMessages($conversation)) {
            $conversationService->save($conversation);
            $this->logInfo("New message arrived before finishing job. Finishing this job.");
            return true;
        }
        
        if ($leadInfo) {
            $conversation->mergeExtractedParameters(['leadInfo' => $leadInfo]);
        }

        if ($sendLeadToClienty) {
            $conversation->mergeExtractedParameters(['sendLeadToClienty' => $sendLeadToClienty]);
            
            $newLead = resolve(LeadService::class)->createFromWapBot($wapBot, $conversation);
            $conversation->leadId = $newLead->id;
            $this->logInfo("leadId: {$newLead->id}");
        }

        if ($nextMessageInfo) {
            $nextMsgButtons = $nextMessageInfo['buttons'] ?? null;
            $nextMsgText = trim((string) ($nextMessageInfo['text'] ?? ''));
            $nextMsgType = strtolower((string) ($nextMessageInfo['type'] ?? 'text'));

            $metaSendResult = $this->sendAndStoreMessage(
                wapBot: $wapBot,
                buttons: $nextMsgButtons,
                messageText: $nextMsgText,
                wapBotConversation: $conversation,
                whatsAppConnection: $whatsAppConnection,
                customerPhoneNumber: $customerPhoneNumber,
            );

            $conversation = $conversationService->addAssistantJsonResponseToOpenAIHistory(
                $conversation, $openAIResponse
            );
            $conversation = $conversationService->addAssistantMessageToOpenAIHistory(
                message: $nextMsgText,
                conversation: $conversation,
                context: ['buttons' => $nextMsgButtons],
                messageType: "assistant_{$nextMsgType}",
                metaMessageId: $metaSendResult['messages'][0]['id'] ?? null,
            );

            $msgInfoStr = json_encode($nextMessageInfo);
            $this->logInfo("Sent message {$msgInfoStr}");
        }

        if ($openAIResponse['isChatEnded'] ?? false) {
            $conversation->markAsEnded();
            $this->logInfo('isChatEnded: true');
        }

        $conversationService->save($conversation);
        $this->logInfo("Finished execution. Returned: '" . ($nextMessageInfo['text'] ?? '') . "'");
        return true;
    }


    private function sendAndStoreMessage(
        WapBot $wapBot,
        WhatsAppMetaAPIConnection $whatsAppConnection,
        string $customerPhoneNumber,
        string $messageText,
        ?array $buttons = null,
        ?string $promptText = null,
        ?WapBotConversation $wapBotConversation = null,
    ): array {
        $whatsAppHelper = resolve(WhatsAppMetaAPIHelper::class);

        if ($promptText) {
            $fullPrompt = "PROMPT DEL SISTEMA: {$promptText}";
            foreach (str_split($fullPrompt, 4000) as $promptChunk) {
                $whatsAppHelper->sendTextMessage(
                    $whatsAppConnection, $customerPhoneNumber, $promptChunk
                );
            }
            $sendResult = [];
        } elseif (!empty($buttons) && count($buttons) > 3) {
            $sendResult = $whatsAppHelper->sendListMessage(
                $whatsAppConnection, $customerPhoneNumber, $messageText, $buttons
            );
        } elseif (!empty($buttons)) {
            $sendResult = $whatsAppHelper->sendButtonsMessage(
                $whatsAppConnection, $customerPhoneNumber, $messageText, $buttons
            );
        } else {
            $sendResult = $whatsAppHelper->sendTextMessage(
                $whatsAppConnection, $customerPhoneNumber, $messageText
            );
        }

        $messageType = empty($buttons) ? 'text' : (count($buttons) > 3 ? 'list' : 'button');

        resolve(WhatsAppEventsDispatcherService::class)->dispatchWapBotSentConversationMessageStoreJob([
            'buttons' => $buttons,
            'wapBotId' => $wapBot->id,
            'messageText' => $messageText,
            'messageType' => $messageType,
            'clientId' => $wapBot->client_id,
            'customerPhoneNumber' => $customerPhoneNumber,
            'wapBotConversationId' => $wapBotConversation?->id,
            'metaMessageId' => $sendResult['messages'][0]['id'] ?? null,
            'connectionPhoneNumberId' => $whatsAppConnection->phone_number_id,
        ]);
        return $sendResult;
    }


    private function buildOpenAIHistoryMessages(array $openAIHistory, string $systemPrompt): array
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];
        foreach ($openAIHistory as $openAIEntry) {
            $role = $openAIEntry['role'] ?? null;
            $messageType = $openAIEntry['message_type'] ?? null;
            $content = (string) ($openAIEntry['message'] ?? '');

            if ($role === 'assistant') {
                if ($messageType === 'assistant_json') {
                    $messages[] = ['role' => 'assistant', 'content' => $content];
                }
                continue;
            }
            if ($role === 'user') {
                $messages[] = ['role' => 'user', 'content' => $content];
            }
        }

        $logMessages = $messages;
        $logMessages[0] = ['role' => 'system', 'content' => '<SYSTEM PROMPT>'];
        $logMessagesStr = json_encode($logMessages);
        $this->logInfo("Mensajes enviados a OPENAI: {$logMessagesStr}");
        return $messages;
    }


    // Obtener respuesta de OpenAI
    private function getOpenAIResponse(array $messages, array $modelParams = []): ?string
    {
        try {
            $apiKey = config('app.openai.api_key');
            if (!$apiKey) {
                $this->logInfo('OpenAI API KEY not found');
                return null;
            }

            $model = $modelParams['model'] ?? 'gpt-5.2';
            $maxTokens = $modelParams['max_tokens'] ?? 800;
            $temperature = $modelParams['temperature'] ?? 0.2;

            if (!isset($modelParams['response_format'])) {
                $responseFormat = [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'ClientyWAPResponse',
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['isChatEnded', 'sendLeadToClienty', 'nextMessage', 'leadInfo'],
                            'properties' => [
                                'isChatEnded' => ['type' => 'boolean'],
                                'sendLeadToClienty' => ['type' => 'boolean'],
                                'nextMessage' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'required' => ['type', 'text', 'buttons'],
                                    'properties' => [
                                        'type' => ['type' => 'string', 'enum' => ['text', 'button', 'image']],
                                        'text' => ['type' => 'string'],
                                        'buttons' => [
                                            'anyOf' => [
                                                [
                                                    'maxItems' => 10,
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string', 'minLength' => 1],
                                                ],
                                                ['type' => 'null']
                                            ]
                                        ],
                                    ],
                                ],
                                'leadInfo' => [
                                    'type' => 'object',
                                    'additionalProperties' => true,
                                ],
                            ],
                        ],
                    ],
                ];
                if (isset($modelParams['json_schema']) && is_array($modelParams['json_schema'])) {
                    $responseFormat['json_schema'] = $modelParams['json_schema'];
                }
            } else {
                $responseFormat = $modelParams['response_format'];
            }

            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'response_format' => $responseFormat,
                    'max_completion_tokens' => $maxTokens,
                ]);
            
            if ($response->successful()) {
                $responseArr = $response->json();
                $aiMessage = $responseArr['choices'][0]['message']['content'] ?? null;
                
                $responseStr = json_encode($responseArr);
                $this->logInfo("Respuesta de OpenAI recibida: {$responseStr}");
                return $aiMessage;
            }
            
            $this->logInfo('Error en respuesta OpenAI: ' . $response->body());
            return null;
        } catch (Exception $e) {
            $this->logInfo('Error al llamar a OpenAI: ' . ((string) $e));
            return null;
        }
    }


    private function parseOpenAIJsonResponse(string $aiResponse): ?array
    {
        try {
            $raw = trim($aiResponse);
            // Remover fences ```json ... ``` si vienen
            if (substr($raw, 0, 3) === '```') {
                // Eliminar primera línea con ```json y última ```
                $raw = preg_replace('/^```[a-zA-Z]*\n?/', '', $raw);
                $raw = preg_replace('/\n?```$/', '', $raw);
                $raw = trim($raw);
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $this->logInfo("OpenAI invalid JSON: {$raw}");
                return null;
            }

            // Normalizar campos esperados
            $result = [
                'nextMessage' => null,
                'isChatEnded' => (bool) ($decoded['isChatEnded'] ?? false),
                'sendLeadToClienty' => (bool) ($decoded['sendLeadToClienty'] ?? false),
                'leadInfo' => is_array($decoded['leadInfo'] ?? null) ? $decoded['leadInfo'] : [],
            ];

            if (isset($decoded['nextMessage']) && is_array($decoded['nextMessage'])) {
                $nextMessage = $decoded['nextMessage'];
                $buttons = $nextMessage['buttons'] ?? null;
                $text = (string) ($nextMessage['text'] ?? '');
                $type = strtolower((string) ($nextMessage['type'] ?? 'text'));
                if (is_array($buttons)) {
                    // Filtrar strings y normalizar
                    $buttons = array_values(array_filter(array_map(function ($b) {
                        return is_string($b) ? trim($b) : null;
                    }, $buttons)));
                }
                $result['nextMessage'] = [
                    'text' => $text,
                    'buttons' => $buttons,
                    'type' => in_array($type, ['text', 'button', 'image']) ? $type : 'text',
                ];
            }
            return $result;
        } catch (Throwable $e) {
            $this->logInfo('Error al parsear JSON OpenAI: ' . ((string) $e));
            return null;
        }
    }


    public function failed(Throwable $e)
    {
        $this->logInfo((string) $e);
        Log::channel('WapBotAnswerIncomingMessageJobErrors')->error((string) $e);
    }

    protected function logInfo(string $msg): void
    {
        $this->getInfoLog()->info("[{$this->logUuid}] | {$msg}");
    }

    protected function getInfoLog()
    {
        return Log::channel('WapBotAnswerIncomingMessageJobInfo');
    }


}
