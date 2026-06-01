<?php

namespace App\Services\API;

use DateTime;
use Throwable;
use Exception;
use DateTimeZone;
use Pusher\Pusher;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Arr;
use App\Helpers\PhonesHelper;
use App\Models\WhatsAppSending;
use App\Models\LeadContactPhone;
use App\Services\API\UserService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\WhatsAppSendingMessage;
use App\Services\Traits\GetUserFromRequest;
use App\Services\API\WhatsAppSendingService;
use App\Services\API\ProposalInfoTmpService;
use App\Services\API\LeadContactPhoneService;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\WAPSender\WAPSenderMessageMediaDTO;
use App\DTO\WAPSender\WAPSenderMessageModalDTO;
use App\Services\API\WhatsAppSendingMessageService;
use App\DTO\WAPSender\WAPSenderNewSendingParametersDTO;
use App\DTO\WAPI\WAPINewWAutomationSendingParametersDTO;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;


class WAPSenderService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function __construct(
        private readonly PhonesHelper $phonesHelper,
        private readonly WhatsAppSendingService $whatsAppSendingService,
        private readonly ProposalInfoTmpService $proposalInfoTmpService,
        private readonly LeadContactPhoneService $leadContactPhoneService,
        private readonly WhatsAppSendingMessageService $whatsAppSendingMessageService,
        private readonly WhatsAppEventsDispatcherService $whatsAppEventsDispatcherService,
        private readonly TimelineEventsDispatcherService $timelineEventsDispatcherService,
    ) {
    }


    public function getMessageModalInfo(Collection $leadIds): WAPSenderMessageModalDTO
    {
        $client = $this->getClient();
        $dto = new WAPSenderMessageModalDTO();
        $dto->leadContactPhones = $this->leadContactPhoneService->findByClientAndLeadIds($client, $leadIds);
        return $dto;
    }


    // $sendDate -> DateTime<Y-m-d> (No trae hora, solo día).
    public function getSendingQuotaInfoByUserAndDate(User $user, DateTime $sendDate): array
    {
        $clientTz = new DateTimeZone($user->client->timezone);
        $sendDate = (new DateTime($sendDate->format('Y-m-d')))->setTimezone(new DateTimeZone('UTC'));
        $sendDateStart = clone($sendDate)->setTime(0, 0, 0)->setTimezone($clientTz);
        $sendDateEnd = clone($sendDate)->setTime(23, 59, 59)->setTimezone($clientTz);

        $sentOrScheduledCount = $this->whatsAppSendingMessageService->countPeriodSentOrScheduledByUserAndType(
            user: $user,
            dateEnd: $sendDateEnd,
            dateStart: $sendDateStart,
            type: WhatsAppSending::WAP_SENDER_JOB_TYPE,
        );
        $dailyUserQuota = $user->client->clientSettings->whatsapp_sender_daily_sending_quota_per_user;
        return [
            'dailyUserQuota' => $dailyUserQuota,
            'dailyUsedQuota' => $sentOrScheduledCount,
        ];
    }


    public function createNewSending(WAPSenderNewSendingParametersDTO $dto): WhatsAppSending
    {
        $this->validateWAPSenderIsEnabled($this->getClient(), $this->getUser());
        if (!$dto->chatMessage) {
            throw new Exception('wap_sender_chat_message_do_not_exists');
        }

        $whatsAppSending = $this->whatsAppSendingService->createNewWAPSenderJobSending($dto);
        if ($whatsAppSending->is_proposal) {
            $proposalInfoTmp = $this->proposalInfoTmpService->createNewByWAPSendingAndDTO($whatsAppSending, $dto);
        }
        if (!$dto->isScheduled()) {
            $this->dispatchWhatsAppSendingMessages($whatsAppSending);
        } else {
            $this->timelineEventsDispatcherService->whatsAppSendingMessagesScheduled($whatsAppSending);
        }
        return $whatsAppSending;
    }


    public function createNewWAutomationSending(WAPINewWAutomationSendingParametersDTO $dto): WhatsAppSending
    {
        $this->validateWAPSenderIsEnabled($dto->client, $dto->user);
        if (!$dto->chatMessage) {
            throw new Exception('wap_sender_chat_message_do_not_exists');
        }

        try {
            DB::beginTransaction();

            $whatsAppSending = $this->whatsAppSendingService->createNewWAPSenderJobWautomationSending($dto);
            $this->whatsAppSendingService->markSendingMessagesAsDispatched($whatsAppSending);
            $this->whatsAppEventsDispatcherService->dispatchSendWAutomationWAPSenderMessagesJobs($whatsAppSending);
            
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        return $whatsAppSending;
    }


    public function dispatchWhatsAppSendingMessages(WhatsAppSending $whatsAppSending): void
    {
        try {
            DB::beginTransaction();

            $this->whatsAppSendingService->markSendingMessagesAsDispatched($whatsAppSending);
            $this->whatsAppEventsDispatcherService->dispatchSendWAPSenderMessagesJobsBySending($whatsAppSending);
            
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    public function dispatchMultipleMessages(Collection $whatsAppSendingMessages): void
    {
        try {
            DB::beginTransaction();

            $this->whatsAppSendingMessageService->markMultipleAsDispatched($whatsAppSendingMessages);
            $this->whatsAppEventsDispatcherService->dispatchMultipleSendWAPSenderMessagesJobs(
                $whatsAppSendingMessages
            );
            
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    protected function validateWAPSenderIsEnabled(Client $client, User $user): void
    {
        if (!$client->clientSettings->enable_whatsapp_sender_job_sending) {
            throw new Exception('client_has_not_enabled_wap_sender');
        }
        if (!$user->wap_sender_session_phone_number) {
            throw new Exception('user_has_not_enabled_wap_sender');
        }
    }


    // PUSHER

    public function listChatMessages(LeadContactPhone $leadContactPhone): Collection
    {
        $client = $leadContactPhone->client;
        $user = $leadContactPhone->lead->user;
        $chatPhoneNumber = $this->phonesHelper->getWhatsAppFormattedPhoneFromLeadContactPhone($leadContactPhone);

        $pusherChannelName = $this->buildPusherChannelName($client->id, $user->wap_sender_session_phone_number);
        $pusherSyncStatusKey = $this->buildPusherMessagesListKey(
            $client->id, $user->wap_sender_session_phone_number, $chatPhoneNumber
        );

        resolve(Pusher::class)->trigger($pusherChannelName, 'getWAPChatMessages', [
            'userId' => $user->id,
            'clientId' => $client->id,
            'phoneNumber' => $chatPhoneNumber,
            'fromPhoneNumber' => $user->wap_sender_session_phone_number,
        ]);

        $response = [];
        $responseReceived = $this->waitForBrowserConfirmation($pusherSyncStatusKey, 30);
        if ($responseReceived) {
            $response = Cache::store('redis')->get($pusherSyncStatusKey);
            if (!($response['success'] ?? false)) {
                throw new Exception($response['error'] ?? 'unknown_error');
            }
        }
        return new Collection($response['data']['chatMessages'] ?? []);
    }


    public function getChatMessageMediaInfo(
        LeadContactPhone $leadContactPhone,
        string $chatMessageId
    ): WAPSenderMessageMediaDTO | null {
        $client = $leadContactPhone->client;
        $user = $leadContactPhone->lead->user;
        if (!$user->wap_sender_session_phone_number) {
            throw new Exception('lead_user_is_not_synced_with_wap_sender');
        }

        $pusherChannelName = $this->buildPusherChannelName($client->id, $user->wap_sender_session_phone_number);
        $pusherSyncStatusKey = $this->buildPusherMessageMediaKey(
            $client->id, $user->wap_sender_session_phone_number, $chatMessageId
        );

        resolve(Pusher::class)->trigger($pusherChannelName, 'getWAPChatMessageMedia', [
            'userId' => $user->id,
            'clientId' => $client->id,
            'chatMessageId' => $chatMessageId,
            'fromPhoneNumber' => $user->wap_sender_session_phone_number,
        ]);

        $response = [];
        $responseReceived = $this->waitForBrowserConfirmation($pusherSyncStatusKey, 30);
        if ($responseReceived) {
            $response = Cache::store('redis')->get($pusherSyncStatusKey);
            if (!($response['success'] ?? false)) {
                throw new Exception($response['error'] ?? 'unknown_error');
            }
        }
        if (!($response['data']['chatMessageMedia']['data'] ?? null)) {
            return null;
        }
        $WAPSenderMessageMediaDTO = new WAPSenderMessageMediaDTO(
            data: $response['data']['chatMessageMedia']['data'],
            mimeType: $response['data']['chatMessageMedia']['mimetype'],
            fileSize: $response['data']['chatMessageMedia']['filesize'] ?? null,
        );
        return $WAPSenderMessageMediaDTO;
    }


    public function buildPusherChannelName(int $clientId, string $wapSenderSessionPhoneNumber): string
    {
        $pusherChannelName = "ClientyWAPSenderChannel-Client-{$clientId}-UserWAPPhone-{$wapSenderSessionPhoneNumber}";
        return $pusherChannelName;
    }
    
    
    public function buildPusherSyncStatusKeyByChannelName(string $pusherChannelName): string
    {
        $pusherSyncStatusKey = "PusherSyncStatus:{$pusherChannelName}";
        return $pusherSyncStatusKey;
    }


    public function buildPusherSyncStatusKey(int $clientId, string $wapSenderSessionPhoneNumber): string
    {
        $pusherChannelName = $this->buildPusherChannelName($clientId, $wapSenderSessionPhoneNumber);
        return $this->buildPusherSyncStatusKeyByChannelName($pusherChannelName);
    }


    public function buildPusherChannelNameByUser(User $user): string
    {
        return $this->buildPusherChannelName($user->client_id, $user->wap_sender_session_phone_number);
    }
    

    public function buildPusherSyncStatusKeyByUser(User $user): string
    {
        return $this->buildPusherSyncStatusKey($user->client_id, $user->wap_sender_session_phone_number);
    }


    public function buildPusherMessagesListKey(
        int $clientId,
        string $wapSenderSessionPhoneNumber,
        string $phoneNumber
    ): string {
        $pusherChannelName = $this->buildPusherChannelName($clientId, $wapSenderSessionPhoneNumber);
        $pusherMessagesListKey = "getChatMessagesFromClienty:{$pusherChannelName}-chatId:{$phoneNumber}";
        return $pusherMessagesListKey;
    }


    public function buildPusherMessageMediaKey(
        int $clientId,
        string $wapSenderSessionPhoneNumber,
        string $chatMessageId
    ): string {
        $pusherChannelName = $this->buildPusherChannelName($clientId, $wapSenderSessionPhoneNumber);
        $pusherMessageMediaKey = "getChatMessageMediaFromClienty:{$pusherChannelName}-chatMessageId:{$chatMessageId}";
        return $pusherMessageMediaKey;
    }


    public function triggerWAPSyncStatusPusherEvent(string|User $userOrChannelName): void
    {
        $pusherChannelName = $userOrChannelName;
        
        if ($userOrChannelName instanceof User) {
            $user = $userOrChannelName;
            if (!$user->wap_sender_session_phone_number) {
                return;
            }
            $pusherChannelName = $this->buildPusherChannelNameByUser($user);
        }
        
        resolve(Pusher::class)->trigger($pusherChannelName, 'getWAPSyncStatus', []);
    }


    // returns [
    //   'allResponses' => [[], [], ...],
    //   'source' => 'redis | extension',
    //   'successResponse' => [...] | null,
    // ]
    //
    // each response: ['timestamp', 'success', 'extensionUUID', 'pusherChannelName']
    public function getWAPSyncStatusResponsesData(User $user, int $maxWaitTimeoutSeconds = 5): array
    {
        $source = 'redis';
        if (!$user->wap_sender_session_phone_number) {
            return ['source' => $source, 'allResponses' => [], 'successResponse' => null];
        }

        $syncStatusResponses = $this->getStoredSyncStatusResponses($user);
        if (!$syncStatusResponses) {
            $source = 'extension';
            // Si no hay nada guardado en Redis, espero respuestas de las extensiones
            sleep($maxWaitTimeoutSeconds);
            // Vuelvo a revisar redis después de esperar
            $syncStatusResponses = $this->getStoredSyncStatusResponses($user);
        }
        $responsesData = [
            'source' => $source,
            'allResponses' => $syncStatusResponses,
            'successResponse' => Arr::first($syncStatusResponses, fn ($item) => ($item['success'] ?? false), null),
        ];
        return $responsesData;
    }


    public function getStoredSyncStatusResponses(User $user): array
    {
        $pusherSyncStatusKey = $this->buildPusherSyncStatusKeyByUser($user);
        $allResponses = Cache::store('redis')->connection()->hgetall($pusherSyncStatusKey);
        
        $decodedResponses = [];
        foreach ($allResponses as $extensionUUID => $responseJson) {
            $decodedResponses[$extensionUUID] = json_decode($responseJson, true);
        }
        return $decodedResponses;
    }


    // $pusherResponse -> ['success', 'error', 'pusherChannelName', 'extensionUUID']
    public function setSyncStatusFromPusherResponse(array $pusherResponse): void
    {
        $responseData = [
            'timestamp' => now()->toISOString(),
            'success' => $pusherResponse['success'],
            'extensionUUID' => $pusherResponse['extensionUUID'],
            'pusherChannelName' => $pusherResponse['pusherChannelName'],
            'extensionVersion' => $pusherResponse['extensionVersion'] ?? null,
        ];
        if (!$pusherResponse['success']) {
            $responseData['error'] = $pusherResponse['error'] ?? 'unknown_error';
        }
        
        $pusherSyncStatusKey = $this->buildPusherSyncStatusKeyByChannelName(
            $pusherResponse['pusherChannelName']
        );
        // Usar Redis HSET para acumular respuestas de forma atómica
        Cache::store('redis')->connection()->hset(
            $pusherSyncStatusKey, $pusherResponse['extensionUUID'], json_encode($responseData)
        );
        Cache::store('redis')->connection()->expire($pusherSyncStatusKey, 30);
    }


    public function sendNewWAPMessage(
        int $userId,
        int $clientId,
        ?array $attachment,
        string $phoneNumber,
        ?string $chatMessage,
        ?int $wAutomationLogId,
        string $fromPhoneNumber,
        string $pusherChannelName,
        string $browserTrackingKey,
        ?string $targetExtensionUUID,
        int $whatsAppSendingMessageId,
    ): void {
        resolve(Pusher::class)->trigger($pusherChannelName, 'sendNewWAPMessage', [
            'userId' => $userId,
            'clientId' => $clientId,
            'attachment' => $attachment,
            'phoneNumber' => $phoneNumber,
            'chatMessage' => $chatMessage,
            'fromPhoneNumber' => $fromPhoneNumber,
            'wAutomationLogId' => $wAutomationLogId,
            'browserTrackingKey' => $browserTrackingKey,
            'targetExtensionUUID' => $targetExtensionUUID,
            'whatsAppSendingMessageId' => $whatsAppSendingMessageId,
        ]);
    }


    // @returns null |
    // [
    //   'error',
    //   'success',
    //   'data' => ['userId', 'clientId', 'browserTrackingKey', 'whatsAppSendingMessageId', 'extensionUUID'],
    // ]
    public function getSentWAPMessageResponse(string $browserTrackingKey, int $maxWaitTimeoutSeconds): ?array
    {
        $sendConfirmationReceived = $this->waitForBrowserConfirmation($browserTrackingKey, $maxWaitTimeoutSeconds);
        if (!$sendConfirmationReceived) {
            return null;
        }
        $sendResult = Cache::store('redis')->get($browserTrackingKey);
        return $sendResult;
    }


    public function waitForBrowserConfirmation(string $redisKey, int $timeoutSeconds): bool
    {
        $start = time();
        while (time() - $start < $timeoutSeconds) {
            $hasResponse = Cache::store('redis')->has($redisKey);
            if ($hasResponse) {
                return true;
            }
            usleep(200000);
        }
        return false;
    }
 
}
