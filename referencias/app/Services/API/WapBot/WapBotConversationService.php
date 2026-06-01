<?php

namespace App\Services\API\WapBot;

use Carbon\Carbon;
use App\Models\Lead;
use App\Models\WapBot;
use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use App\Models\WhatsAppMetaAPIConnection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\MongoDB\WapBot\WapBotConversation;
use App\DTO\WapBot\WhatsAppMetaAPIWebhookPayloadDTO;
use App\Repositories\WapBot\WapBotConversationRepository;
use App\DTO\ClientyConfigurations\WapBotSeedConversationsUploadDTO;
use App\Repositories\Criteria\Filter\WapBotConversations\TypeCriteria;
use App\Repositories\Criteria\Filter\WapBotConversations\LeadIdCriteria;
use App\Repositories\Criteria\Filter\WapBotConversations\HasLeadCriteria;
use App\Repositories\Criteria\Filter\WapBotConversations\IsEndedCriteria;
use App\Repositories\Criteria\Filter\WapBotConversations\DateEndCriteria;
use App\Repositories\Criteria\Filter\WapBotConversations\DateStartCriteria;
use App\Repositories\Criteria\Filter\WapBotConversations\CustomerPhoneNumberCriteria;
use App\Repositories\Criteria\Filter\WapBotConversations\BotMetaPhoneNumberIdCriteria;


class WapBotConversationService
{

    private const OPEN_AI_HISTORY_LIMIT = 200;


    public function __construct(
        protected readonly WapBotConversationRepository $conversationRepository
    ) {
    }


    public function find(string $id): WapBotConversation
    {
        return $this->conversationRepository->find($id);
    }


    public function findLatestConversation(
        int $clientId,
        string $botMetaPhoneNumberId,
        string $customerPhoneNumber
    ): ?WapBotConversation {
        return $this->conversationRepository->findLatestConversation(
            clientId: $clientId,
            customerPhoneNumber: $customerPhoneNumber,
            botMetaPhoneNumberId: $botMetaPhoneNumberId
        );
    }


    public function findReferralDataByLead(Lead $lead): ?array
    {
        $conversation = $this->conversationRepository->findByClientAndLead(
            leadId: $lead->id,
            clientId: $lead->client_id,
        );
        return $conversation?->referralData;
    }


    public function cursorByClientWithLeadAndReferralData(
        int $clientId,
        array $columns = ['_id', 'clientId', 'leadId', 'referralData', 'createdAt']
    ): LazyCollection {
        return $this->conversationRepository->cursorByClientWithLeadAndReferralData(
            clientId: $clientId,
            columns: $columns,
        );
    }


    public function findConversationsNeedingFollowUp(
        WapBot $wapBot,
        int $windowMinutes = 60,
    ): Collection {
        $delayMinutes = $wapBot->followup_1_delay_minutes;
        if (!$delayMinutes) {
            return new Collection();
        }

        // Conversaciones cuyo último mensaje fue hace entre (delay) y (delay + window) minutos
        // lastActivityDateEnd = hace $delayMinutes (el límite más reciente - conversaciones que YA pasaron el delay)
        // lastActivityDateStart = $delayMinutes + $windowMinutes (el límite más antiguo - no queremos ir tan atrás)
        $lastActivityDateEnd = now()->subMinutes($delayMinutes)->toDateTime();
        $lastActivityDateStart = now()->subMinutes($delayMinutes + $windowMinutes)->toDateTime();

        return $this->conversationRepository->findWithPendingFollowUp(
            wapBot: $wapBot,
            lastActivityDateEnd: $lastActivityDateEnd,
            lastActivityDateStart: $lastActivityDateStart,
        );
    }
    

    public function findConversationsForAutoLeadCreation(
        WapBot $wapBot,
        int $windowMinutes = 60,
    ): Collection {
        $delayMinutes = $wapBot->auto_create_lead_after_minutes;
        if (!$delayMinutes) {
            return new Collection();
        }

        // Conversaciones cuyo último mensaje fue hace entre (delay) y (delay + window) minutos
        $lastActivityDateEnd = now()->subMinutes($delayMinutes)->toDateTime();
        $lastActivityDateStart = now()->subMinutes($delayMinutes + $windowMinutes)->toDateTime();

        return $this->conversationRepository->findConversationsForAutoLeadCreation(
            wapBot: $wapBot,
            lastActivityDateEnd: $lastActivityDateEnd,
            lastActivityDateStart: $lastActivityDateStart,
        );
    }


    public function createBotConversation(
        WapBot $wapBot,
        WhatsAppMetaAPIConnection $connection,
        string $customerPhoneNumber,
        ?string $lastMetaMessageId = null,
        ?string $lastMetaMessageTimestamp = null,
        ?array $referralData = null,
    ): WapBotConversation {
        $conversation = new WapBotConversation([
            'leadId' => null,
            'isEnded' => false,
            'openAIHistory' => [],
            'wapBotId' => $wapBot->id,
            'userId' => $wapBot->user_id,
            'referralData' => $referralData,
            'lastActivityAt' => Carbon::now(),
            'clientId' => $connection->client_id,
            'lastMetaMessageId' => $lastMetaMessageId,
            'whatsAppConnectionId' => $connection->id,
            'customerPhoneNumber' => $customerPhoneNumber,
            'botPhoneNumber' => $connection->phone_number,
            'type' => WapBotConversation::TYPE_BOT_CONVERSATION,
            'botMetaPhoneNumberId' => $connection->phone_number_id,
            'lastMetaMessageTimestamp' => $lastMetaMessageTimestamp,
            'clientyWapBotId' => $wapBot->clienty_wap_bot_id ?? null,
            'threadId' => 'thr_' . Str::uuid()->toString(),
            'extractedParameters' => [
                'isChatEnded' => false, 'sendLeadToClienty' => false, 'leadInfo' => [],
            ],
        ]);

        return $this->conversationRepository->create($conversation);
    }


    public function markAsEnded(WapBotConversation $conversation, bool $isEnded = true): WapBotConversation
    {
        $conversation->isEnded = $isEnded;
        $conversation->extractedParameters = array_replace_recursive(
            $conversation->extractedParameters ?? [],
            ['isChatEnded' => $isEnded]
        );
        return $conversation;
    }


    public function markAsEndedByLeadAutoCreationCron(WapBotConversation $conversation): WapBotConversation
    {
        $conversation->isEnded = true;
        $conversation->isEndedByLeadAutoCreationCron = true;
        $conversation->extractedParameters = array_replace_recursive(
            $conversation->extractedParameters ?? [],
            ['isChatEnded' => true, 'isEndedByLeadAutoCreationCron' => true]
        );
        return $conversation;
    }


    public function getRecentOpenAIHistory(WapBotConversation $conversation, int $limit = 20): array
    {
        $openAIHistory = $conversation->openAIHistory ?? [];
        return array_slice($openAIHistory, -$limit);
    }


    public function addUserMessageToOpenAIHistory(
        WapBotConversation $conversation,
        string $message,
        string $messageType,
        ?string $metaMessageId,
        array $context = [],
    ): WapBotConversation {
        return $this->addEntryToOpenAIHistory(
            conversation: $conversation,
            entry: [
                'role' => 'user',
                'message' => $message,
                'context' => $context,
                'message_type' => $messageType,
                'meta_message_id' => $metaMessageId,
            ],
        );
    }


    public function addAssistantMessageToOpenAIHistory(
        WapBotConversation $conversation,
        string $message,
        string $messageType,
        ?string $metaMessageId,
        array $context = [],
    ): WapBotConversation {
        return $this->addEntryToOpenAIHistory(
            conversation: $conversation,
            entry: [
                'role' => 'assistant',
                'message' => $message,
                'context' => $context,
                'message_type' => $messageType,
                'meta_message_id' => $metaMessageId,
            ],
        );
    }


    public function addAssistantJsonResponseToOpenAIHistory(
        WapBotConversation $conversation,
        array $payload
    ): WapBotConversation {
        return $this->addEntryToOpenAIHistory(
            conversation: $conversation,
            entry: [
                'context' => null,
                'role' => 'assistant',
                'meta_message_id' => null,
                'message_type' => 'assistant_json',
                'message' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        );
    }


    private function addEntryToOpenAIHistory(
        WapBotConversation $conversation,
        array $entry,
    ): WapBotConversation {
        if (!isset($entry['at'])) {
            $entry['at'] = now()->toIso8601String();
        }

        $openAIHistory = $conversation->openAIHistory ?? [];
        $openAIHistory[] = $entry;
        if (count($openAIHistory) > self::OPEN_AI_HISTORY_LIMIT) {
            $openAIHistory = array_slice($openAIHistory, -self::OPEN_AI_HISTORY_LIMIT);
        }
        $conversation->openAIHistory = $openAIHistory;
        return $conversation;
    }



    public function updateReceivedMetaMessageInfo(
        WapBotConversation $conversation,
        string $lastMetaMessageId,
        string $lastMetaMessageTimestamp,
    ): WapBotConversation {
        $updateData = [
            'lastActivityAt' => Carbon::now(),
            'lastMetaMessageId' => $lastMetaMessageId,
            'lastMetaMessageTimestamp' => $lastMetaMessageTimestamp,
        ];
        $conversation->fill($updateData);
        $conversation->save();
        // $ok = $this->conversationRepository->update($conversation, $updateData);
        return $conversation;
    }


    /**
     * Registra el momento en que el vendedor (no el bot) le escribió manualmente al customer.
     * Se usa para que el bot no intervenga si el cliente responde dentro de la ventana de reactivación.
     * Update puntual (no reescribe el documento completo).
     */
    public function updateLastSentMessageToCustomerAt(WapBotConversation $conversation): WapBotConversation
    {
        $conversation->lastSentMessageToCustomerAt = Carbon::now();
        $conversation->save();
        return $conversation;
    }


    public function hasReceivedNewMessages(WapBotConversation $conversation): bool
    {
        if (!$conversation->lastMetaMessageId) {
            return false;
        }
        return $this->conversationRepository->hasReceivedNewMessages(
            conversation: $conversation,
            currentLastMetaMessageId: $conversation->lastMetaMessageId,
        );
    }


    public function save(WapBotConversation $conversation): WapBotConversation
    {
        return $this->conversationRepository->save($conversation);
    }


    public function delete(WapBotConversation $conversation): bool
    {
        return $this->conversationRepository->delete($conversation);
    }


    public function createUploadedSeedConversations(WapBot $wapBot, WapBotSeedConversationsUploadDTO $dto): array
    {
        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        $connection = $wapBot->whatsAppMetaAPIConnection;

        if (!$connection) {
            return [
                'createdCount' => 0,
                'updatedCount' => 0,
                'skippedCount' => 0,
                'error' => 'no_connection_found',
            ];
        }

        foreach ($dto->csvRows as $csvRow) {
            $customerPhoneNumber = $csvRow['customerPhoneNumber'];
            $lastActivityDate = $csvRow['lastActivityDate'];

            // Buscar si ya existe una conversación semilla con este teléfono
            $existingConversation = $this->conversationRepository->findSeedConversationByPhoneNumber(
                clientId: $wapBot->client_id,
                botMetaPhoneNumberId: $wapBot->meta_phone_number_id,
                customerPhoneNumber: $customerPhoneNumber
            );

            if ($existingConversation) {
                // Si existe, verificar si la fecha cambió
                $existingActivityDate = $existingConversation->lastActivityAt->toDateTimeString();
                if ($existingActivityDate !== $lastActivityDate->toDateTimeString()) {
                    // Actualizar la fecha
                    $existingConversation->lastActivityAt = $lastActivityDate;
                    $this->conversationRepository->save($existingConversation);
                    $updatedCount++;
                } else {
                    // No cambió, skip
                    $skippedCount++;
                }
            } else {
                // No existe, crear nueva
                $newConversation = new WapBotConversation([
                    'leadId' => null,
                    'isEnded' => true,
                    'clientId' => $wapBot->client_id,
                    'lastActivityAt' => $lastActivityDate,
                    'threadId' => 'thr_' . Str::uuid()->toString(),
                    'customerPhoneNumber' => $customerPhoneNumber,
                    'botPhoneNumber' => $connection->phone_number,
                    'type' => WapBotConversation::TYPE_HISTORY_SEED,
                    'botMetaPhoneNumberId' => $wapBot->meta_phone_number_id,
                ]);

                $this->conversationRepository->create($newConversation);
                $createdCount++;
            }
        }

        return [
            'createdCount' => $createdCount,
            'updatedCount' => $updatedCount,
            'skippedCount' => $skippedCount,
        ];
    }


    public function createOutgoingMessageSeedConversation(
        WapBot $wapBot,
        WhatsAppMetaAPIWebhookPayloadDTO $dto
    ): WapBotConversation {
        return $this->createNewSeedConversation(
            wapBot: $wapBot,
            lastActivityAt: $dto->getTimestamp(),
            botPhoneNumber: $dto->getFromNumber(),
            customerPhoneNumber: $dto->getToNumber(),
        );
    }


    public function createNewSeedConversation(
        WapBot $wapBot,
        int $lastActivityAt,
        string $botPhoneNumber,
        string $customerPhoneNumber,
    ): WapBotConversation {
        $newConversation = new WapBotConversation([
            'leadId' => null,
            'isEnded' => true,
            'clientId' => $wapBot->client_id,
            'lastActivityAt' => $lastActivityAt,
            'botPhoneNumber' => $botPhoneNumber,
            'isCreatedFromOutgoingEchoMessage' => true,
            'customerPhoneNumber' => $customerPhoneNumber,
            'threadId' => 'thr_' . Str::uuid()->toString(),
            'lastSentMessageToCustomerAt' => Carbon::now(),
            'type' => WapBotConversation::TYPE_HISTORY_SEED,
            'botMetaPhoneNumberId' => $wapBot->meta_phone_number_id,
        ]);
        return $this->conversationRepository->create($newConversation);
    }


    public function createOrUpdatePermanentSeedConversation(
        WapBot $wapBot,
        string $customerPhoneNumber
    ): array {
        $connection = $wapBot->whatsAppMetaAPIConnection;

        if (!$connection) {
            return [
                'created' => false,
                'updated' => false,
                'error' => 'no_connection_found',
            ];
        }

        // 1. Marcar como isEnded=true todas las conversaciones no-semilla activas
        $activeNonSeedConversations = $this->conversationRepository->findActiveNonSeedConversations(
            clientId: $wapBot->client_id,
            botMetaPhoneNumberId: $wapBot->meta_phone_number_id,
            customerPhoneNumber: $customerPhoneNumber
        );

        foreach ($activeNonSeedConversations as $conversation) {
            $conversation->isEnded = true;
            $this->conversationRepository->save($conversation);
        }

        // 2. Buscar si ya existe una conversación semilla con este teléfono
        $existingSeedConversation = $this->conversationRepository->findSeedConversationByPhoneNumber(
            clientId: $wapBot->client_id,
            botMetaPhoneNumberId: $wapBot->meta_phone_number_id,
            customerPhoneNumber: $customerPhoneNumber
        );

        if ($existingSeedConversation) {
            // Actualizar la conversación semilla existente
            $existingSeedConversation->lastActivityAt = Carbon::now();
            $existingSeedConversation->isPermanentSeedConversation = true;
            $this->conversationRepository->save($existingSeedConversation);

            return [
                'created' => false,
                'updated' => true,
                'endedConversationsCount' => $activeNonSeedConversations->count(),
            ];
        }

        // 3. Crear nueva conversación semilla permanente
        $newConversation = new WapBotConversation([
            'leadId' => null,
            'isEnded' => true,
            'clientId' => $wapBot->client_id,
            'lastActivityAt' => Carbon::now(),
            'isPermanentSeedConversation' => true,
            'customerPhoneNumber' => $customerPhoneNumber,
            'botPhoneNumber' => $connection->phone_number,
            'threadId' => 'thr_' . Str::uuid()->toString(),
            'type' => WapBotConversation::TYPE_HISTORY_SEED,
            'botMetaPhoneNumberId' => $wapBot->meta_phone_number_id,
        ]);

        $this->conversationRepository->create($newConversation);

        return [
            'created' => true,
            'updated' => false,
            'endedConversationsCount' => $activeNonSeedConversations->count(),
        ];
    }


    public function list(Client $client, array $options): LengthAwarePaginator
    {
        $opts = [
            'page' => $options['page'] ?? 1,
            'with' => $options['with'] ?? [],
            'limit' => $options['limit'] ?? 10,
            'withTrashed' => $options['withTrashed'] ?? false,
            'sort' => $this->getSortCriteriasByName($options['sort'] ?? ''),
            'filters' => $this->getFilterCriteriasByName($options['filters'] ?? []),
        ];
        $response = $this->conversationRepository->listPaginated($client->id, $opts);
        return $response;
    }


    public function getModalInfo(Client $client, WapBotConversation $conversation): WapBotConversation
    {
        // Eager load de relaciones del lead
        if ($conversation->leadId) {
            $conversation->load([
                'lead',
                'lead.user',
                'lead.tags',
                'lead.status',
                'lead.mainLeadContact',
                'lead.leadContacts',
            ]);
        }
        return $conversation;
    }


    protected function getFilterCriteriasByName(array $filters): array
    {
        $criterias = [
            'type' => TypeCriteria::class,
            'leadId' => LeadIdCriteria::class,
            'hasLead' => HasLeadCriteria::class,
            'isEnded' => IsEndedCriteria::class,
            'dateEnd' => DateEndCriteria::class,
            'dateStart' => DateStartCriteria::class,
            'customerPhoneNumber' => CustomerPhoneNumberCriteria::class,
            'botMetaPhoneNumberId' => BotMetaPhoneNumberIdCriteria::class,
        ];

        $nfilters = [];
        foreach ($filters as $key => $value) {
            if (!$value && $value !== false) {
                continue;
            }
            if (in_array($key, array_keys($criterias)) && $value !== null) {
                $nfilters[$key] = new $criterias[$key]($value);
            } else {
                $nfilters[$key] = $value;
            }
        }
        return $nfilters;
    }


    private function getSortCriteriasByName($sortsName)
    {
        $sortTypes = [
            'date_asc' => 'createdAt asc',
            'date_desc' => 'createdAt desc',
        ];
        return $sortsName ? $sortTypes[$sortsName] : $sortsName;
    }

}
