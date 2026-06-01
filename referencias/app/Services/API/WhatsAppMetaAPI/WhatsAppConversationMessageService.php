<?php

namespace App\Services\API\WhatsAppMetaAPI;

use Exception;
use App\Models\User;
use App\Models\Client;
use App\Models\LeadContactPhone;
use App\Models\WhatsAppTemplate;
use App\Services\API\LeadService;
use App\Models\WhatsAppAttachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\WhatsAppSendingMessage;
use App\Helpers\WhatsAppVariablesHelper;
use App\Helpers\WhatsAppAttachmentHelper;
use App\Models\WhatsAppMetaAPIConnection;
use App\Services\API\LeadContactPhoneService;
use App\DTO\WapBot\WhatsAppMetaAPIWebhookPayloadDTO;
use App\DTO\WhatsAppMetaAPI\WhatsAppConversationMessageDTO;
use App\Models\MongoDB\WhatsAppMetaAPI\WhatsAppConversationMessage;
use App\Helpers\WhatsAppMetaAPI\WhatsAppConversationAttachmentHelper;
use App\Repositories\WhatsAppMetaAPI\WhatsAppConversationMessageRepository;


class WhatsAppConversationMessageService
{

    public function __construct(
        protected readonly WhatsAppConversationMessageRepository $whatsAppConversationMessageRepository,
        protected readonly LeadService $leadService,
        protected readonly LeadContactPhoneService $leadContactPhoneService,
    ) {
    }


    public function findOneByHash(string $hash): ?WhatsAppConversationMessage
    {
        return $this->whatsAppConversationMessageRepository->findOneByHash($hash);
    }


    public function findOneByPayloadDTO(WhatsAppMetaAPIWebhookPayloadDTO $payloadDTO): ?WhatsAppConversationMessage
    {
        $model = WhatsAppConversationMessage::fillFromMetaPayloadDTO($payloadDTO);
        $wapConversationMsg = $this->findOneByHash($model->hash);
        return $wapConversationMsg;
    }


    public function findOneByMetaMessageId(
        string $metaConnectedPhoneNumberId,
        string $customerPhoneNumber,
        string $metaMessageId
    ): ?WhatsAppConversationMessage {
        return $this->whatsAppConversationMessageRepository->findOneByMetaMessageId(
            $metaConnectedPhoneNumberId, $customerPhoneNumber, $metaMessageId
        );
    }


    public function listConversation(
        string $metaConnectedPhoneNumberId,
        string $customerPhoneNumber,
        array $opts = []
    ): Collection {
        $conversationMsgs = $this->whatsAppConversationMessageRepository->listConversation(
            $metaConnectedPhoneNumberId, $customerPhoneNumber, $opts
        );
        return $conversationMsgs->reverse()->values();
    }


    public function listConversations(Client $client, User $user, array $opts = []): array
    {
        $userIds = $opts['user_id'] ?? null;
        $customerPhoneNumber = $opts['customerPhoneNumber'] ?? null;
        // filters.lead agrupa filtros relativos al prospecto: status/tag (múltiples) + id puntual.
        // Se separan porque tienen caminos distintos: status/tag resuelven a N teléfonos;
        // id puntual resuelve a los teléfonos de un prospecto específico.
        $rawLeadFilters = $opts['filters']['lead'] ?? [];
        $leadIdSearch = $rawLeadFilters['id'] ?? null;
        $leadFilters = array_filter([
            'status_id' => $rawLeadFilters['status_id'] ?? [],
            'tag_id' => $rawLeadFilters['tag_id'] ?? [],
        ], fn($v) => !empty($v));
        $permission = $client->clientSettings->whatsapp_meta_api_conversations_permission;

        // 'none': nadie ve conversaciones
        if ($permission === 'none') {
            return ['conversations' => [], 'conversationsTotalCount' => 0];
        }

        $whatsappMetaAPIConnections = resolve(WhatsAppMetaAPIService::class)->findConnectionsByClient($client);
        $isOwnerRestricted = in_array($permission, ['owner_only', 'owner_leads_only']);

        // Obtener y filtrar conexiones según permiso y tipo de usuario
        if ($isOwnerRestricted) {
            // Con restricción owner, TODOS los usuarios (incluidos admins) solo ven sus propias conexiones
            $whatsappMetaAPIConnections = $whatsappMetaAPIConnections->where('user_id', $user->id)->values();
        } elseif ($user->type !== 'admin') {
            $whatsappMetaAPIConnections = $whatsappMetaAPIConnections->where('user_id', $user->id)->values();
        } elseif (!empty($userIds)) {
            $whatsappMetaAPIConnections = $whatsappMetaAPIConnections->whereIn('user_id', $userIds)->values();
        }

        if ($whatsappMetaAPIConnections->isEmpty()) {
            return ['conversations' => [], 'conversationsTotalCount' => 0];
        }

        // Restricción leads_only: solo conversaciones con prospecto asociado
        $onlyWithLeadsPermission = in_array($permission, ['leads_only', 'owner_leads_only']);
        // Para búsqueda puntual con restricción leads_only: verificar que el phone tiene lead
        if ($onlyWithLeadsPermission && $customerPhoneNumber) {
            $hasLead = $this->leadContactPhoneService->normalizedLeadContactPhoneExists($client, $customerPhoneNumber);
            if (!$hasLead) {
                return ['conversations' => [], 'conversationsTotalCount' => 0];
            }
        }

        // Si estoy filtrando por un solo número (usado en real-time), no aplico filtros de búsqueda.
        if (!$customerPhoneNumber) {
            if ($leadFilters) {
                $allowedPhones = $this->leadContactPhoneService->findNormalizedPhonesByLeadFilters(
                    $client, $leadFilters
                );
                if (empty($allowedPhones)) {
                    return ['conversations' => [], 'conversationsTotalCount' => 0];
                }
                $opts['customerPhoneNumbers'] = $allowedPhones;
            }

            // Búsqueda por ID de prospecto: se resuelve a sus teléfonos normalizados.
            // Si ya había phones permitidos (por status/tag), se intersecta.
            if ($leadIdSearch) {
                $leadPhones = $this->leadContactPhoneService->findNormalizedPhonesByLeadId(
                    $client, $leadIdSearch
                );
                if (empty($leadPhones)) {
                    return ['conversations' => [], 'conversationsTotalCount' => 0];
                }
                if (isset($opts['customerPhoneNumbers'])) {
                    $opts['customerPhoneNumbers'] = array_values(
                        array_intersect($opts['customerPhoneNumbers'], $leadPhones)
                    );
                    if (empty($opts['customerPhoneNumbers'])) {
                        return ['conversations' => [], 'conversationsTotalCount' => 0];
                    }
                } else {
                    $opts['customerPhoneNumbers'] = $leadPhones;
                }
            }

            // Búsqueda por substring de teléfono: se propaga al repo como regex sobre customerPhoneNumber.
            $phoneNumberSearch = $opts['filters']['customerPhoneNumberSearch'] ?? null;
            if ($phoneNumberSearch) {
                $opts['customerPhoneNumberSearch'] = $phoneNumberSearch;
            }
        }

        $metaConnectedPhoneNumberIds = $whatsappMetaAPIConnections->pluck('phone_number_id')->toArray();
        $result = $this->whatsAppConversationMessageRepository->listConversations(
            $metaConnectedPhoneNumberIds, $opts
        );

        // Indexar mensajes por clave (metaConnectedPhoneNumberId + customerPhone)
        $messagesIndex = [];
        foreach ($result['messagesPerConversation'] ?? [] as $group) {
            $key = $group['id']['metaConnectedPhoneNumberId'] . '|' . $group['id']['customerPhoneNumber'];
            $messagesIndex[$key] = $group['messages'] ?? [];
        }

        // Combinar conversaciones con sus mensajes transformados vía DTO
        $conversations = [];
        foreach ($result['conversations'] as $conv) {
            $customerPhone = $conv['id']['customerPhoneNumber'];
            $metaConnectedPhoneNumberId = $conv['id']['metaConnectedPhoneNumberId'];
            $key = $metaConnectedPhoneNumberId . '|' . $customerPhone;

            $rawMessages = $messagesIndex[$key] ?? [];
            $messages = [];
            foreach ($rawMessages as $rawMsg) {
                $msgModel = new WhatsAppConversationMessage();
                $msgModel->_id = $rawMsg['_id'];
                $msgModel->media = $rawMsg['media'] ?? null;
                $msgModel->source = $rawMsg['source'] ?? null;
                $msgModel->direction = $rawMsg['direction'] ?? null;
                $msgModel->metaError = $rawMsg['metaError'] ?? null;
                $msgModel->metaStatus = $rawMsg['metaStatus'] ?? null;
                $msgModel->messageType = $rawMsg['messageType'] ?? null;
                $msgModel->metaMessageId = $rawMsg['metaMessageId'] ?? null;
                $msgModel->metaRawPayload = $rawMsg['metaRawPayload'] ?? null;
                $msgModel->metaReceivedMessageTimestamp = $rawMsg['metaReceivedMessageTimestamp'] ?? null;
                $dto = new WhatsAppConversationMessageDTO($msgModel);
                $messages[] = $dto->toArray();
            }

            $lastMessageAt = $conv['lastMessageAt'] ?? null;
            if ($lastMessageAt instanceof \MongoDB\BSON\UTCDateTime) {
                $lastMessageAt = $lastMessageAt->toDateTime()->format('c');
            }

            $lastIncomingMessageAt = $conv['lastIncomingMessageAt'] ?? null;
            if ($lastIncomingMessageAt instanceof \MongoDB\BSON\UTCDateTime) {
                $lastIncomingMessageAt = $lastIncomingMessageAt->toDateTime()->format('c');
            }

            $connectionsIndex = $whatsappMetaAPIConnections->keyBy('phone_number_id');
            $connection = $connectionsIndex[$metaConnectedPhoneNumberId] ?? null;

            $customerName = null;
            foreach ($rawMessages as $rawMsg) {
                if (($rawMsg['direction'] ?? null) === 'incoming') {
                    $customerName = data_get(
                        $rawMsg, 'metaRawPayload.entry.0.changes.0.value.contacts.0.profile.name'
                    );
                    break;
                }
            }
            
            $conversations[] = [
                'messages' => $messages,
                'customerName' => $customerName,
                'lastMessageAt' => $lastMessageAt,
                'connectionId' => $connection->id ?? null,
                'customerPhoneNumber' => $customerPhone,
                'totalMessages' => $conv['totalMessages'] ?? 0,
                'lastIncomingMessageAt' => $lastIncomingMessageAt,
                'metaConnectedPhoneNumberId' => $metaConnectedPhoneNumberId,
                'connectionPhoneNumber' => $connection->phone_number ?? null,
                'connectionVerifiedName' => $connection->phone_number_verified_name ?? null,
            ];
        }

        // Enriquecer con leads asociados por número de teléfono
        $customerPhones = array_unique(array_column($conversations, 'customerPhoneNumber'));
        $leadsMap = $this->leadService->findLeadsByNormalizedPhones($client, $customerPhones);

        foreach ($conversations as &$conv) {
            $leads = $leadsMap[$conv['customerPhoneNumber']] ?? [];
            $conv['leads'] = array_map(function ($lead) {
                $mainContact = $lead->leadContacts->first();
                return [
                    'id' => $lead->id,
                    'contactName' => $mainContact
                        ? trim(($mainContact->name ?? '') . ' ' . ($mainContact->last_name ?? ''))
                        : null,
                    'contactEmails' => $mainContact
                        ? $mainContact->leadContactEmails->pluck('email')->toArray()
                        : [],
                    'contactPhones' => $mainContact
                        ? $mainContact->leadContactPhones->pluck('phone')->toArray()
                        : [],
                    'status' => $lead->status ? [
                        'id' => $lead->status->id,
                        'name' => $lead->status->name,
                        'background_color' => $lead->status->background_color,
                        'text_color' => $lead->status->text_color,
                    ] : null,
                    'tags' => $lead->tags->map(fn ($tag) => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'text_color' => $tag->text_color,
                        'background_color' => $tag->background_color,
                    ])->toArray(),
                    'userName' => $lead->user
                        ? trim(($lead->user->name ?? '') . ' ' . ($lead->user->last_name ?? ''))
                        : null,
                ];
            }, $leads);
        }
        unset($conv);

        // Cantidad de conversaciones que devolvió MongoDB (antes del post-filtro).
        // El frontend lo usa para saber si se agotaron las páginas de MongoDB,
        // ya que conversationsTotalCount no refleja el post-filtro.
        $rawConversationsCount = count($conversations);

        // Post-filtro: solo conversaciones con prospecto asociado (evita query masiva de phones)
        if ($onlyWithLeadsPermission) {
            $conversations = array_values(array_filter($conversations, fn($c) => !empty($c['leads'])));
        }

        return [
            'conversations' => $conversations,
            'conversationsTotalCount' => $result['totalCount'],
            'rawConversationsCount' => $rawConversationsCount,
        ];
    }


    public function findOneById(string $id): ?WhatsAppConversationMessage
    {
        return $this->whatsAppConversationMessageRepository->findOneById($id);
    }


    public function getMediaTemporaryUrl(WhatsAppConversationMessage $msg, int $minutes = 10): ?string
    {
        if (!is_array($msg->media)) {
            return null;
        }

        $clientyWhatsAppAttachmentId = $msg->media['clientyWhatsAppAttachmentId'] ?? null;
        if ($clientyWhatsAppAttachmentId) {
            $wapAttachment = WhatsAppAttachment::find($clientyWhatsAppAttachmentId);
            if (!$wapAttachment) {
                return null;
            }
            return resolve(WhatsAppAttachmentHelper::class)->getTemporaryUrl($wapAttachment, $minutes);
        }

        $clientyFileInfo = $msg->media['clientyFileInfo'] ?? null;
        if (!is_array($clientyFileInfo) || empty($clientyFileInfo['bucketFilePath'])) {
            return null;
        }
        $mimeType = $msg->media['mime_type'] ?? 'application/octet-stream';
        return resolve(WhatsAppConversationAttachmentHelper::class)->getTemporaryUrl(
            $clientyFileInfo['bucketFilePath'], $mimeType, $minutes
        );
    }


    public function createFromWebhookPayloadDTO(
        WhatsAppMetaAPIWebhookPayloadDTO $payloadDTO
    ): WhatsAppConversationMessage {
        return $this->whatsAppConversationMessageRepository->createFromWebhookPayloadDTO($payloadDTO);
    }


    public function createFromSentAPIMessage(WhatsAppSendingMessage $wapSendingMsg): WhatsAppConversationMessage
    {
        $wapSending = $wapSendingMsg->whatsAppSending;
        $wapTemplate = $wapSending->whatsAppTemplate; // null para mensajes libres
        $connection = $wapSendingMsg->user->whatsAppMetaAPIConnection;
        $isOpenMessage = !$wapTemplate;

        // Construir secciones comunes del rawPayload
        $rawPayload = [
            'clienty' => [
                'whatsAppSendingMessageId' => $wapSendingMsg->id,
                'whatsAppSendingId' => $wapSendingMsg->whatsapp_sending_id,
                'leadId' => $wapSendingMsg->lead_id,
                'leadContactPhoneId' => $wapSendingMsg->lead_contact_phone_id,
                'userId' => $wapSendingMsg->user_id,
                'clientId' => $wapSendingMsg->client_id,
                'wautomationLogId' => $wapSendingMsg->wautomation_log_id,
            ],
            'meta' => [
                'messageId' => $wapSendingMsg->meta_id,
                'status' => $wapSendingMsg->meta_status,
                'phoneNumberId' => $connection->phone_number_id,
                'sentTimestamp' => $wapSendingMsg->sent_date?->timestamp,
            ],
            'recipient' => [
                'phoneNumber' => $wapSendingMsg->phone_number,
            ],
        ];

        $attachment = null;
        $messageType = 'text';

        if ($isOpenMessage) {
            // Mensaje libre: extraer texto plano del JSON guardado
            $wapSendingMessageText = $wapSending->whatsAppSendingMessageText;
            $messageTextJson = json_decode($wapSendingMessageText->message, true);
            $chatMessageText = $messageTextJson['body'] ?? $wapSendingMessageText->message;

            $rawPayload['message'] = [
                'type' => 'text',
                'text' => $chatMessageText,
            ];
        } else {
            // Mensaje con template: resolver attachment, variables y renderizar
            $attachment = $wapSending->whatsAppAttachment ?? $wapTemplate->whatsAppAttachment;

            $bodyVariables = $this->buildOrderedVariablesArray(
                $wapTemplate->body, $wapSendingMsg->leadContactPhone, $wapSendingMsg->user
            );
            $headerVariables = $this->buildOrderedVariablesArray(
                $wapTemplate->meta_header_text, $wapSendingMsg->leadContactPhone, $wapSendingMsg->user
            );
            $renderedBody = $this->renderTemplateText($wapTemplate->body, $bodyVariables);
            $renderedHeader = $this->renderTemplateText($wapTemplate->meta_header_text, $headerVariables);

            $rawPayload['template'] = [
                'id' => $wapTemplate->id,
                'metaId' => $wapTemplate->meta_id,
                'name' => $wapTemplate->meta_name,
                'category' => $wapTemplate->meta_category,
                'bodyTemplate' => $wapTemplate->body,
                'headerTemplate' => $wapTemplate->meta_header_text,
                'footerTemplate' => $wapTemplate->meta_footer_text,
                'bodyVariables' => $bodyVariables,
                'headerVariables' => $headerVariables,
                'renderedBody' => $renderedBody,
                'renderedHeader' => $renderedHeader,
            ];
            $rawPayload['attachment'] = $attachment ? [
                'clientyWhatsAppAttachmentId' => $attachment->id,
                'metaId' => $attachment->meta_handle_id,
                'metaMediaType' => $attachment->getMetaMediaType(),
                'mimeType' => $attachment->mime_type,
                'filename' => $attachment->original_filename,
                'size' => $attachment->size,
                'bucketName' => $attachment->bucket_name,
                'bucketFilePath' => $attachment->bucket_filepath,
            ] : null;

            $messageType = $attachment ? $attachment->getMetaMediaType() : 'text';
        }

        $rawPayload['clienty']['whatsAppAttachmentId'] = $attachment?->id;

        // Construir modelo
        $model = new WhatsAppConversationMessage();
        $model->source = WhatsAppConversationMessage::SOURCE_API_MESSAGE;
        $model->direction = WhatsAppConversationMessage::DIRECTION_OUTGOING;
        $model->messageType = $messageType;
        $model->metaMessageId = $wapSendingMsg->meta_id;
        $model->metaReceivedMessageTimestamp = $wapSendingMsg->sent_date;
        $model->metaConnectedPhoneNumberId = $connection->phone_number_id;
        $model->customerPhoneNumber = $wapSendingMsg->phone_number;
        $model->metaRawPayload = $rawPayload;
        $model->media = $attachment ? [
            'clientyWhatsAppAttachmentId' => $attachment->id,
            'metaId' => $attachment->meta_handle_id,
            'metaMediaType' => $attachment->getMetaMediaType(),
            'mimeType' => $attachment->mime_type,
            'filename' => $attachment->original_filename,
            'size' => $attachment->size,
            'bucketName' => $attachment->bucket_name,
            'bucketFilePath' => $attachment->bucket_filepath,
        ] : null;
        $model->hash = $model->buildHash();

        if (!$model->save()) {
            throw new Exception('Failed to save WhatsAppConversationMessage from sent message');
        }

        return $model->fresh();
    }


    public function createFromWapBotMessage(array $data): WhatsAppConversationMessage
    {
        $rawPayload = [
            'clienty' => [
                'wapBotId' => $data['wapBotId'],
                'clientId' => $data['clientId'],
                'wapBotConversationId' => $data['wapBotConversationId'] ?? null,
            ],
            'meta' => [
                'sentTimestamp' => now()->timestamp,
                'messageId' => $data['metaMessageId'],
                'phoneNumberId' => $data['connectionPhoneNumberId'],
            ],
            'recipient' => [
                'phoneNumber' => $data['customerPhoneNumber'],
            ],
            'message' => [
                'type' => $data['messageType'],
                'text' => $data['messageText'],
                'buttons' => $data['buttons'] ?? null,
            ],
        ];

        $model = new WhatsAppConversationMessage();
        $model->media = null;
        $model->metaRawPayload = $rawPayload;
        $model->messageType = $data['messageType'];
        $model->metaReceivedMessageTimestamp = now();
        $model->metaMessageId = $data['metaMessageId'];
        $model->customerPhoneNumber = $data['customerPhoneNumber'];
        $model->source = WhatsAppConversationMessage::SOURCE_WAP_BOT_MESSAGE;
        $model->direction = WhatsAppConversationMessage::DIRECTION_OUTGOING;
        $model->metaConnectedPhoneNumberId = $data['connectionPhoneNumberId'];
        
        $model->hash = $model->buildHash();
        if (!$model->save()) {
            throw new Exception('Failed to save WhatsAppConversationMessage from WapBot message');
        }

        return $model->fresh();
    }


    /**
     * Crea un WhatsAppConversationMessage a partir de un envío a no-prospecto.
     * No depende de WhatsAppSendingMessage (no se persiste en MySQL).
     * El service se encarga de cargar el template (si aplica) y armar toda la estructura del rawPayload.
     * Semántica del rawPayload: similar a createFromSentAPIMessage (secciones clienty, meta, recipient).
     */
    public function createFromNonLeadSentMessage(
        int $userId,
        int $clientId,
        ?string $metaMessageId,
        string $customerPhoneNumber,
        WhatsAppMetaAPIConnection $whatsAppMetaAPIConnection,
        ?string $chatMessage = null,
        ?WhatsAppTemplate $whatsAppTemplate = null,
        array $bodyVariables = [],
        array $headerVariables = [],
        ?array $conversationMessageMedia = null,
    ): WhatsAppConversationMessage {
        $rawPayload = [
            'clienty' => [
                'isNonLead' => true,
                'userId' => $userId,
                'clientId' => $clientId,
                'whatsAppAttachmentId' => null,
            ],
            'meta' => [
                'sentTimestamp' => now()->timestamp,
                'messageId' => $metaMessageId,
                'phoneNumberId' => $whatsAppMetaAPIConnection->phone_number_id,
            ],
            'recipient' => [
                'phoneNumber' => $customerPhoneNumber,
            ],
        ];

        $whatsAppAttachment = null;
        $conversationMessageMedia = $conversationMessageMedia ?? [];
        $metaMediaType = $conversationMessageMedia['metaMediaType'] ?? null;
        $isVoiceNote = (bool) ($conversationMessageMedia['isVoiceNote'] ?? false);
        $metaMediaId = $conversationMessageMedia['metaId'] ?? $conversationMessageMedia['id'] ?? null;

        // Mensaje libre de texto
        if ($chatMessage) {
            $rawPayload['message'] = ['type' => 'text', 'text' => $chatMessage];
        }

        // Mensaje de media (ej: audio grabado desde el navegador, subido a Meta sin persistir en S3)
        if ($metaMediaId && $metaMediaType) {
            $rawPayload['message'] = [
                'isVoiceNote' => $isVoiceNote,
                'type' => $metaMediaType,
                'metaMediaId' => $metaMediaId,
            ];
        }
        // Template: usa el modelo ya cargado en el job para evitar reconsultar la BD.
        if ($whatsAppTemplate) {
            $renderedBody = $this->renderTemplateText($whatsAppTemplate->body, $bodyVariables);
            $renderedHeader = $this->renderTemplateText($whatsAppTemplate->meta_header_text, $headerVariables);

            $rawPayload['template'] = [
                'id' => $whatsAppTemplate->id,
                'metaId' => $whatsAppTemplate->meta_id,
                'name' => $whatsAppTemplate->meta_name,
                'category' => $whatsAppTemplate->meta_category,
                'bodyTemplate' => $whatsAppTemplate->body,
                'headerTemplate' => $whatsAppTemplate->meta_header_text,
                'footerTemplate' => $whatsAppTemplate->meta_footer_text,
                'bodyVariables' => $bodyVariables,
                'headerVariables' => $headerVariables,
                'renderedBody' => $renderedBody,
                'renderedHeader' => $renderedHeader,
            ];

            $whatsAppAttachment = $whatsAppTemplate->whatsAppAttachment;
            if ($whatsAppAttachment) {
                $rawPayload['attachment'] = [
                    'clientyWhatsAppAttachmentId' => $whatsAppAttachment->id,
                    'metaId' => $whatsAppAttachment->meta_handle_id,
                    'metaMediaType' => $whatsAppAttachment->getMetaMediaType(),
                    'mimeType' => $whatsAppAttachment->mime_type,
                    'filename' => $whatsAppAttachment->original_filename,
                    'size' => $whatsAppAttachment->size,
                    'bucketName' => $whatsAppAttachment->bucket_name,
                    'bucketFilePath' => $whatsAppAttachment->bucket_filepath,
                ];
                $rawPayload['clienty']['whatsAppAttachmentId'] = $whatsAppAttachment->id;
            }
        }

        $media = $conversationMessageMedia;
        $messageType = ($metaMediaId && $metaMediaType)
            ? ($isVoiceNote ? 'voice' : $metaMediaType)
            : 'text'
        ;
        if ($whatsAppAttachment) {
            $messageType = $whatsAppAttachment->getMetaMediaType();
            $media = [
                'clientyWhatsAppAttachmentId' => $whatsAppAttachment->id,
                'metaId' => $whatsAppAttachment->meta_handle_id,
                'metaMediaType' => $whatsAppAttachment->getMetaMediaType(),
                'mimeType' => $whatsAppAttachment->mime_type,
                'filename' => $whatsAppAttachment->original_filename,
                'size' => $whatsAppAttachment->size,
                'bucketName' => $whatsAppAttachment->bucket_name,
                'bucketFilePath' => $whatsAppAttachment->bucket_filepath,
            ];
        }

        $model = new WhatsAppConversationMessage();
        $model->media = $media;
        $model->messageType = $messageType;
        $model->metaRawPayload = $rawPayload;
        $model->metaMessageId = $metaMessageId;
        $model->metaReceivedMessageTimestamp = now();
        $model->customerPhoneNumber = $customerPhoneNumber;
        $model->source = WhatsAppConversationMessage::SOURCE_API_MESSAGE;
        $model->direction = WhatsAppConversationMessage::DIRECTION_OUTGOING;
        $model->metaConnectedPhoneNumberId = $whatsAppMetaAPIConnection->phone_number_id;

        $model->hash = $model->buildHash();
        if (!$model->save()) {
            throw new Exception('Failed to save WhatsAppConversationMessage from non-lead sent message');
        }

        return $model->fresh();
    }


    /**
     * Ordena variables named según la aparición en el texto del template.
     * Replica la lógica de WhatsAppMetaAPIService::buildOrderedVariablesArray()
     */
    private function buildOrderedVariablesArray(
        ?string $bodyOrHeaderText,
        LeadContactPhone $leadContactPhone,
        User $user,
    ): array {
        if (!$bodyOrHeaderText) {
            return [];
        }

        preg_match_all('/{{\s*([A-Za-z0-9_]+)\s*}}/', $bodyOrHeaderText, $match);
        $orderedVarNames = $match[1] ?? [];
        if (!$orderedVarNames) {
            return [];
        }

        $extractedVars = WhatsAppVariablesHelper::getVariablesArray(
            user: $user,
            fallbackValue: '—',
            chatMessage: $bodyOrHeaderText,
            leadContactPhone: $leadContactPhone,
        );

        $params = [];
        foreach ($orderedVarNames as $varName) {
            $params[] = [
                'type' => 'text',
                'parameter_name' => (string) $varName,
                'text' => (string) ($extractedVars[$varName] ?? '—'),
            ];
        }
        return $params;
    }


    /**
     * Reemplaza {{varName}} con los valores resueltos en el texto del template.
     */
    private function renderTemplateText(?string $text, array $variables): ?string
    {
        if (!$text || empty($variables)) {
            return $text;
        }
        foreach ($variables as $var) {
            $text = preg_replace(
                '/\{\{\s*' . preg_quote($var['parameter_name'], '/') . '\s*\}\}/',
                $var['text'],
                $text,
                1
            );
        }
        return $text;
    }

}
