<?php

namespace App\Repositories\WhatsAppMetaAPI;

use Exception;
use App\Models\User;
use App\Models\Client;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Repositories\Traits\VoidClearCache;
use App\DTO\WapBot\WhatsAppMetaAPIWebhookPayloadDTO;
use App\Models\MongoDB\WhatsAppMetaAPI\WhatsAppConversationMessage;


class WhatsAppConversationMessageRepository implements Repository
{

    use VoidClearCache;


    public function findOneById(string $id): ?WhatsAppConversationMessage
    {
        return WhatsAppConversationMessage::find($id);
    }

    public function findOneByHash(string $hash): ?WhatsAppConversationMessage
    {
        return WhatsAppConversationMessage::where('hash', $hash)->first();
    }


    public function findOneByMetaMessageId(
        string $metaConnectedPhoneNumberId,
        string $customerPhoneNumber,
        string $metaMessageId
    ): ?WhatsAppConversationMessage {
        return WhatsAppConversationMessage::where('metaConnectedPhoneNumberId', $metaConnectedPhoneNumberId)
            ->where('customerPhoneNumber', $customerPhoneNumber)
            ->where('metaMessageId', $metaMessageId)
            ->first();
    }


    public function listConversation(
        string $metaConnectedPhoneNumberId,
        string $customerPhoneNumber,
        array $opts = []
    ): Collection {
        $limit = $opts['limit'] ?? 200;
        $converstationMsgs = WhatsAppConversationMessage::where('customerPhoneNumber', $customerPhoneNumber)
            ->where('metaConnectedPhoneNumberId', $metaConnectedPhoneNumberId)
            ->orderBy('metaReceivedMessageTimestamp', 'desc')
            ->limit($limit)
            ->get()
        ;
        return $converstationMsgs;
    }


    public function listConversations(array $metaConnectedPhoneNumberIds, array $opts = []): array
    {
        $page = $opts['page'] ?? 1;
        $limit = (int) ($opts['limit'] ?? 25);
        $skip = ($page - 1) * $limit;

        // Filtro base: mensajes de las conexiones permitidas
        $baseMatch = ['metaConnectedPhoneNumberId' => ['$in' => $metaConnectedPhoneNumberIds]];

        // Filtros opcionales para buscar una conversación puntual (real-time).
        // metaConnectedPhoneNumberId se valida contra los permitidos para no bypasear permisos.
        $customerPhoneNumber = $opts['customerPhoneNumber'] ?? null;
        $customerPhoneNumbers = $opts['customerPhoneNumbers'] ?? [];
        $customerPhoneNumberSearch = $opts['customerPhoneNumberSearch'] ?? null;
        $messagesPerConversation = (int) ($opts['messagesPerConversation'] ?? 3);
        $metaConnectedPhoneNumberId = $opts['metaConnectedPhoneNumberId'] ?? null;

        // Real-time puntual (exact match) tiene precedencia sobre los filtros de búsqueda.
        if ($customerPhoneNumber) {
            $baseMatch['customerPhoneNumber'] = $customerPhoneNumber;
        } else {
            // Combina $in (phones permitidos por lead filters o búsqueda por leadId)
            // con $regex (búsqueda por substring del teléfono). Ambos pueden coexistir.
            $customerPhoneConditions = [];
            if ($customerPhoneNumbers) {
                $customerPhoneConditions['$in'] = array_values($customerPhoneNumbers);
            }
            if ($customerPhoneNumberSearch) {
                $customerPhoneConditions['$regex'] = preg_quote($customerPhoneNumberSearch);
            }
            if ($customerPhoneConditions) {
                $baseMatch['customerPhoneNumber'] = $customerPhoneConditions;
            }
        }

        if ($metaConnectedPhoneNumberId && in_array($metaConnectedPhoneNumberId, $metaConnectedPhoneNumberIds)) {
            $baseMatch['metaConnectedPhoneNumberId'] = $metaConnectedPhoneNumberId;
        }

        // Pipeline 1: Conversaciones paginadas
        $conversationsPipeline = [
            ['$match' => $baseMatch],
            [
                '$group' => [
                    '_id' => [
                        'customerPhoneNumber' => '$customerPhoneNumber',
                        'metaConnectedPhoneNumberId' => '$metaConnectedPhoneNumberId',
                    ],
                    'lastMessageAt' => ['$max' => '$metaReceivedMessageTimestamp'],
                    'lastIncomingMessageAt' => [
                        '$max' => [
                            '$cond' => [
                                ['$eq' => ['$direction', 'incoming']],
                                '$metaReceivedMessageTimestamp',
                                null,
                            ],
                        ]
                    ],
                    'totalMessages' => ['$sum' => 1],
                ]
            ],
            ['$sort' => ['lastMessageAt' => -1]],
            ['$skip' => $skip],
            ['$limit' => $limit],
        ];

        $conversations = WhatsAppConversationMessage::raw(function ($collection) use ($conversationsPipeline) {
            return $collection->aggregate($conversationsPipeline);
        })->toArray();

        // Pipeline 1b: Count total (mismo filtro base)
        $countPipeline = [
            ['$match' => $baseMatch],
            [
                '$group' => [
                    '_id' => [
                        'customerPhoneNumber' => '$customerPhoneNumber',
                        'metaConnectedPhoneNumberId' => '$metaConnectedPhoneNumberId',
                    ],
                ]
            ],
            ['$count' => 'total'],
        ];

        $countResult = WhatsAppConversationMessage::raw(function ($collection) use ($countPipeline) {
            return $collection->aggregate($countPipeline);
        })->toArray();
        $totalCount = $countResult[0]['total'] ?? 0;

        if (empty($conversations)) {
            return ['conversations' => [], 'totalCount' => $totalCount];
        }

        // Extraer phone number ids y customer phones de la página actual
        $pagePhoneNumberIds = [];
        $pageCustomerPhones = [];
        foreach ($conversations as $conv) {
            $pagePhoneNumberIds[] = $conv['id']['metaConnectedPhoneNumberId'];
            $pageCustomerPhones[] = $conv['id']['customerPhoneNumber'];
        }
        $pagePhoneNumberIds = array_unique($pagePhoneNumberIds);
        $pageCustomerPhones = array_unique($pageCustomerPhones);

        // Pipeline 2: Últimos 3 mensajes de cada conversación
        $messagesPipeline = [
            [
                '$match' => [
                    'metaConnectedPhoneNumberId' => ['$in' => array_values($pagePhoneNumberIds)],
                    'customerPhoneNumber' => ['$in' => array_values($pageCustomerPhones)],
                ]
            ],
            ['$sort' => ['metaReceivedMessageTimestamp' => -1]],
            [
                '$group' => [
                    '_id' => [
                        'customerPhoneNumber' => '$customerPhoneNumber',
                        'metaConnectedPhoneNumberId' => '$metaConnectedPhoneNumberId',
                    ],
                    'messages' => [
                        '$push' => [
                            '_id' => '$_id',
                            'media' => '$media',
                            'source' => '$source',
                            'direction' => '$direction',
                            'metaError' => '$metaError',
                            'metaStatus' => '$metaStatus',
                            'messageType' => '$messageType',
                            'metaMessageId' => '$metaMessageId',
                            'metaRawPayload' => '$metaRawPayload',
                            'metaReceivedMessageTimestamp' => '$metaReceivedMessageTimestamp',
                        ]
                    ],
                ]
            ],
            [
                '$project' => [
                    'messages' => ['$slice' => ['$messages', $messagesPerConversation]],
                ]
            ],
        ];

        $messagesResult = WhatsAppConversationMessage::raw(function ($collection) use ($messagesPipeline) {
            return $collection->aggregate($messagesPipeline);
        })->toArray();

        return [
            'conversations' => $conversations,
            'messagesPerConversation' => $messagesResult,
            'totalCount' => $totalCount,
        ];
    }


    public function createFromWebhookPayloadDTO(
        WhatsAppMetaAPIWebhookPayloadDTO $payloadDTO
    ): WhatsAppConversationMessage {
        $wapConversationMsg = WhatsAppConversationMessage::fillFromMetaPayloadDTO($payloadDTO);
        $wapConversationMsg->save();
        if (!$wapConversationMsg->save()) {
            throw new Exception('Failed to save WhatsAppConversationMessage');
        }
        return $wapConversationMsg->fresh();
    }

}
