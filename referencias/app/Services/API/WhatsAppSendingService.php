<?php

namespace App\Services\API;

use DateTime;
use Exception;
use Throwable;
use DateTimeZone;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Models\WhatsAppSending;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\WhatsAppSendingMessage;
use App\Helpers\WhatsAppVariablesHelper;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\WAPI\WAPINewSendingParametersDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\WhatsAppSendingRepository;
use App\Services\API\WhatsAppSendingMessageService;
use App\Services\API\WhatsAppSendingMessageTextService;
use App\DTO\WAPSender\WAPSenderNewSendingParametersDTO;
use App\DTO\WAPI\WAPINewWAutomationSendingParametersDTO;
use App\DTO\WhatsAppSenderExtension\WhatsAppSenderPopUpInfoDTO;
use App\Repositories\Criteria\Sort\WhatsAppSending\SortBySendDate;
use App\DTO\WhatsAppMetaAPI\WhatsAppMetaAPINewSendingParametersDTO;
use App\DTO\WhatsAppSenderExtension\WhatsAppSenderCreateSendingDTO;
use App\Repositories\Criteria\Filter\WhatsAppSending\LeadIdCriteria;
use App\Repositories\Criteria\Filter\WhatsAppSending\IsMassiveCriteria;
use App\Repositories\Criteria\Filter\WhatsAppSending\SendDateEndCriteria;
use App\Repositories\Criteria\Filter\WhatsAppSending\IsAutomationCriteria;
use App\Repositories\Criteria\Filter\WhatsAppSending\SendDateStartCriteria;
use App\Repositories\Criteria\Filter\WhatsAppSending\SendStatusFilterCriteria;


class WhatsAppSendingService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function __construct(
        private readonly WhatsAppSendingRepository $whatsAppSendingRepository,
        private readonly WhatsAppSendingMessageService $whatsAppSendingMessageService,
        private readonly WhatsAppSendingMessageTextService $whatsAppSendingMessageTextService,
        private readonly int $minimumAppVersion,
    ) {
    }


    public function listWhatsAppSendingMessagesToExport(WhatsAppSending $wapSending, array $options): Collection
    {
        $wapSendingMessages = $wapSending->whatsAppSendingMessages();
        $relationshipsToEagerLoad = $options['with'] ?? [];
        if ($relationshipsToEagerLoad) {
            $wapSendingMessages->with($relationshipsToEagerLoad);
        }
        return $wapSendingMessages->get();
    }


    public function getWapSenderPopUpInfoDTO(User $user, int $whatsAppSenderAppVersion): WhatsAppSenderPopUpInfoDTO
    {
        $dto = new WhatsAppSenderPopUpInfoDTO($whatsAppSenderAppVersion, $this->minimumAppVersion);

        // if ($whatsAppSenderAppVersion >= $this->minimumAppVersion) {
            $type = WhatsAppSending::WAP_SENDER_TYPE;
            $dailyUsedQuota = $this->getDailyUsedSendingQuotaByUserAndType($user, $type);
            $dailyUserQuota = $user->client->clientSettings->whatsapp_sender_daily_sending_quota_per_user;
            
            $dto->dailyUserQuota = $dailyUserQuota;
            $dto->dailyUsedQuota = $dailyUsedQuota;
            $dto->lastSending = $this->findLastByUserAndType($user, $type);
            $dto->currentSending = $this->findCurrentSendingByUserAndType($user, $type);
            $dto->dailyRemainingQuota = $dailyUserQuota - $dailyUsedQuota;
            $dto->quotaPerSending = $user->client->clientSettings->whatsapp_sender_quota_per_sending;
        // }
        return $dto;
    }


    public function markMessageAsSent(
        WhatsAppSendingMessage $wapSendingMsg,
        bool $success,
        ?string $errorMessage = null
    ): WhatsAppSendingMessage {
        $wapSendingMsg = $this->whatsAppSendingMessageService->markAsSent($wapSendingMsg, $success, $errorMessage);
        $this->setFirstAndLastSentMessageDate($wapSendingMsg);
        return $wapSendingMsg;
    }


    public function markWhatsAppMetaAPIMessageAsSent(
        WhatsAppSendingMessage $wapSendingMsg,
        array $metaResponse,
        bool $success,
    ): WhatsAppSendingMessage {
        $wapSendingMsg = $this->whatsAppSendingMessageService->markAsSent($wapSendingMsg, $success);

        $metaId = $metaResponse['messages'][0]['id'];
        $metaIdHash = WhatsAppSendingMessage::buildMetaIdHash($metaId);
        $metaStatus = $metaResponse['messages'][0]['message_status'] ?? 'open_msg_sent';
        $wapSendingMsg->fill(['meta_id' => $metaId, 'meta_status' => $metaStatus, 'meta_id_hash' => $metaIdHash]);
        $wapSendingMsg->saveOrFail();

        $this->setFirstAndLastSentMessageDate($wapSendingMsg);
        return $wapSendingMsg;
    }


    public function markAsFailedIfAllMessagesFailed(
        WhatsAppSending $wapSending,
        string $failReason
    ): WhatsAppSending {
        $nonFailedMsgs = $wapSending->whatsAppSendingMessages->filter(function ($wapMsg) {
            return $wapMsg->success !== false;
        });
        if ($nonFailedMsgs->isNotEmpty()) {
            return $wapSending;
        }

        $attrs = ['failed_date' => new DateTime('now'), 'fail_reason' => $failReason];
        return $this->whatsAppSendingRepository->update($wapSending, $attrs);
    }


    public function findProposalsBetweenSentDatesByClient(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd
    ): Collection {
        return $this->whatsAppSendingRepository->findProposalsBetweenSentDatesByClient($client, $dateStart, $dateEnd);
    }


    public function findWAPIScheduledEnabledToSendBetweenSendDatesByClient(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd,
        array $opts = []
    ): Collection {
        return $this->whatsAppSendingRepository->findWAPIScheduledEnabledToSendBetweenSendDatesByClient(
            $client, $dateStart, $dateEnd, $opts
        );
    }


    public function findCurrentSendingByUserAndType(User $user, string $type): ?WhatsAppSending
    {
        return $this->whatsAppSendingRepository->findCurrentSendingByUserAndType($user, $type);
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->whatsAppSendingRepository->findByClientAndIds($client, $ids);
    }


    public function findLastByUserAndType(User $user, string $type): ?WhatsAppSending
    {
        return $this->whatsAppSendingRepository->findLastByUserAndType($user, $type);
    }


    public function list(array $options, ?Client $client = null): LengthAwarePaginator
    {
        $opts = [
            'page' => $options['page'] ?? 1,
            'with' => $options['with'] ?? [],
            'limit' => $options['limit'] ?? 10,
            'sort' => $this->getSortCriteriasByName($options['sort'] ?? ''),
            'filters' => $this->getFilterCriteriasByName($options['filters'] ?? []),
        ];
        $client = $client ?? $this->getClient();
        $response = $this->whatsAppSendingRepository->listPaginated($client, $opts);
        return $response;
    }


    //
    // Usado por la extensión en el browser (no es para crear jobs)
    //
    public function createNewWAPSenderSending(WhatsAppSenderCreateSendingDTO $dto): WhatsAppSending
    {
        $currentSending = $this->findCurrentSendingByUserAndType($dto->user, WhatsAppSending::WAP_SENDER_TYPE);
        if ($currentSending) {
            throw new Exception('opened_whatsapp_sending_already_exists');
        }
        $chatMessage = $dto->chatMessage;
        $phonesMapDTO = $dto->phonesMapDTO;
        $isMassive = $dto->phonesMapDTO->phonesMap->pluck('leadId')->unique()->values()->count() > 1;

        if (!$chatMessage || $phonesMapDTO->isEmpty()) {
            throw new Exception('missing_whatsapp_sending_data');
        }

        try {
            DB::beginTransaction();

            $wapMsgTxt = $this->whatsAppSendingMessageTextService->findOrCreate($chatMessage);
            $wapSendingData = [
                'is_massive' => $isMassive,
                'user_id' => $dto->user->id,
                'client_id' => $dto->user->client_id,
                'type' => WhatsAppSending::WAP_SENDER_TYPE,
                'whatsapp_sending_message_text_id' => $wapMsgTxt->id,
            ];
            $wapSending = $this->whatsAppSendingRepository->create($wapSendingData);
            $wapSendingMsgs = $this->whatsAppSendingMessageService->createMultipleWapSenderMessages(
                $wapSending, $phonesMapDTO, ['hasVariables' => WhatsAppVariablesHelper::hasVariables($chatMessage)]
            );
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $wapSending;
    }


    public function createNewWAPISending(
        WAPINewSendingParametersDTO | WAPINewWAutomationSendingParametersDTO $dto
    ): WhatsAppSending {
        $isUserSending = ($dto instanceof WAPINewSendingParametersDTO);
        $isWAutomationSending = ($dto instanceof WAPINewWAutomationSendingParametersDTO);

        if (!$dto->chatMessage) {
            throw new Exception('create_wapi_sending_missing_chat_message');
        }
        if ($isUserSending && $dto->leadContactPhones->isEmpty()) {
            throw new Exception('create_wapi_sending_missing_lead_phones');
        }
        if ($isWAutomationSending && $dto->wAutomationWAPSendingDataCollection->isEmpty()) {
            throw new Exception('create_wapi_sending_missing_wautomation_logs');
        }

        try {
            DB::beginTransaction();
            $user = $isWAutomationSending ? $dto->user : $this->getUser();
            $client = $isWAutomationSending ? $dto->client : $this->getClient();
            $wapMsgTxt = $this->whatsAppSendingMessageTextService->findOrCreate($dto->chatMessage);
            $wapSendingData = [
                'user_id' => $user->id,
                'client_id' => $client->id,
                'is_massive' => $dto->isMassive,
                'is_proposal' => $dto->isProposal,
                'type' => WhatsAppSending::WAPI_TYPE,
                'send_date' => $dto->sendDate ?? now(),
                'is_automation' => $isWAutomationSending,
                'whatsapp_attachment_id' => $dto->attachment?->id,
                'whatsapp_sending_message_text_id' => $wapMsgTxt->id,
            ];
            $wapSending = $this->whatsAppSendingRepository->create($wapSendingData);
            $wapSendingMsgs = $this->whatsAppSendingMessageService->createMultipleWAPISendingMessages(
                $wapSending, $dto
            );
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $wapSending;
    }


    public function createNewWhatsAppMetaAPISending(
        WhatsAppTemplate $whatsAppTemplate,
        WhatsAppMetaAPINewSendingParametersDTO $dto
    ): WhatsAppSending {
        $isUserSending = ($dto instanceof WhatsAppMetaAPINewSendingParametersDTO);
        // $isWAutomationSending = ($dto instanceof WhatsAppMetaAPINewWAutomationSendingParametersDTO);
        $isWAutomationSending = false;

        if ($isUserSending && $dto->leadContactPhones->isEmpty()) {
            throw new Exception('create_whatsapp_sending_missing_lead_phones');
        }
        if ($isWAutomationSending && $dto->wAutomationWAPSendingDataCollection->isEmpty()) {
            throw new Exception('create_whatsapp_sending_missing_wautomation_logs');
        }

        try {
            DB::beginTransaction();

            $user = $isWAutomationSending ? $dto->user : $this->getUser();
            $client = $isWAutomationSending ? $dto->client : $this->getClient();
            $whatsAppTemplateMetaTextStr = $whatsAppTemplate->getMetaCompleteTextJson();
            $wapMessageText = $this->whatsAppSendingMessageTextService->findOrCreate($whatsAppTemplateMetaTextStr);

            $wapSendingData = [
                'user_id' => $user->id,
                'client_id' => $client->id,
                'is_massive' => $dto->isMassive,
                'is_proposal' => $dto->isProposal,
                'send_date' => $dto->sendDate ?? now(),
                'is_automation' => $isWAutomationSending,
                'whatsapp_template_id' => $whatsAppTemplate->id,
                'type' => WhatsAppSending::WHATSAPP_META_API_TYPE,
                'whatsapp_sending_message_text_id' => $wapMessageText->id,
                'whatsapp_attachment_id' => $dto->whatsAppAttachment?->id ?? $whatsAppTemplate->whatsapp_attachment_id,
            ];
            $wapSending = $this->whatsAppSendingRepository->create($wapSendingData);
            $wapSendingMsgs = $this->whatsAppSendingMessageService->createMultipleWhatsAppMetaAPISendingMessages(
                $wapSending, $whatsAppTemplate, $dto
            );

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        return $wapSending;
    }


    public function createNewWhatsAppMetaAPIOpenSending(
        WhatsAppMetaAPINewSendingParametersDTO $dto
    ): WhatsAppSending {
        if ($dto->leadContactPhones->isEmpty()) {
            throw new Exception('create_whatsapp_sending_missing_lead_phones');
        }

        try {
            DB::beginTransaction();

            $user = $this->getUser();
            $client = $this->getClient();
            $chatMessage = $dto->chatMessage;
            $chatMessageJson = json_encode(['body' => $chatMessage]);
            $wapMessageText = $this->whatsAppSendingMessageTextService->findOrCreate($chatMessageJson);

            $wapSendingData = [
                'user_id' => $user->id,
                'client_id' => $client->id,
                'is_massive' => false,
                'is_proposal' => $dto->isProposal,
                'send_date' => now(),
                'is_automation' => false,
                'whatsapp_template_id' => null,
                'type' => WhatsAppSending::WHATSAPP_META_API_TYPE,
                'whatsapp_sending_message_text_id' => $wapMessageText->id,
                'whatsapp_attachment_id' => null,
            ];
            $wapSending = $this->whatsAppSendingRepository->create($wapSendingData);
            $wapSendingMsgs = $this->whatsAppSendingMessageService->createMultipleWhatsAppMetaAPIOpenSendingMessages(
                $wapSending, $dto
            );

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        return $wapSending;
    }


    public function createNewWhatsAppMetaAPIWautomationSending(
        WhatsAppTemplate $whatsAppTemplate,
        WAPINewWAutomationSendingParametersDTO $dto,
    ): WhatsAppSending {
        if ($dto->wAutomationWAPSendingDataCollection->isEmpty()) {
            throw new Exception('create_whatsapp_sending_missing_wautomation_logs');
        }

        try {
            DB::beginTransaction();

            $whatsAppTemplateMetaTextStr = $whatsAppTemplate->getMetaCompleteTextJson();
            $wapMessageText = $this->whatsAppSendingMessageTextService->findOrCreate($whatsAppTemplateMetaTextStr);

            $wapSendingData = [
                'is_massive' => false,
                'is_automation' => true,
                'user_id' => $dto->user->id,
                'client_id' => $dto->client->id,
                'is_proposal' => $dto->isProposal,
                'send_date' => $dto->sendDate ?? now(),
                'whatsapp_template_id' => $whatsAppTemplate->id,
                'whatsapp_attachment_id' => $dto->attachment?->id,
                'type' => WhatsAppSending::WHATSAPP_META_API_TYPE,
                'whatsapp_sending_message_text_id' => $wapMessageText->id,
            ];
            $wapSending = $this->whatsAppSendingRepository->create($wapSendingData);
            $wapSendingMsgs = $this->whatsAppSendingMessageService->createMultipleWhatsAppMetaAPIWAutomationMessages(
                $wapSending, $whatsAppTemplate, $dto
            );

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $wapSending;
    }


    public function createNewWAPSenderJobSending(WAPSenderNewSendingParametersDTO $dto): WhatsAppSending
    {
        if (!$dto->chatMessage) {
            throw new Exception('create_whatsapp_sending_missing_chat_message');
        }
        if ($dto->leadContactPhones->isEmpty()) {
            throw new Exception('create_whatsapp_sending_missing_lead_phones');
        }

        try {
            DB::beginTransaction();

            $user = $this->getUser();
            $client = $this->getClient();
            $wapMsgTxt = $this->whatsAppSendingMessageTextService->findOrCreate($dto->chatMessage);
            $wapSendingData = [
                'is_automation' => false,
                'is_proposal' => $dto->isProposal,
                'user_id' => $this->getUser()->id,
                'client_id' => $this->getClient()->id,
                'send_date' => $dto->sendDate ?? now(),
                'is_massive' => $dto->isMassive ?? false,
                'type' => WhatsAppSending::WAP_SENDER_JOB_TYPE,
                'whatsapp_attachment_id' => $dto->attachment?->id,
                'whatsapp_sending_message_text_id' => $wapMsgTxt->id,
            ];
            $wapSending = $this->whatsAppSendingRepository->create($wapSendingData);
            $wapSendingMsgs = $this->whatsAppSendingMessageService->createMultipleWAPSenderJobSendingMessages(
                $wapSending, $dto
            );
            
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $wapSending;
    }


    public function createNewWAPSenderJobWautomationSending(
        WAPINewWAutomationSendingParametersDTO $dto
    ): WhatsAppSending {
        if (!$dto->chatMessage) {
            throw new Exception('create_whatsapp_sending_missing_chat_message');
        }
        if ($dto->wAutomationWAPSendingDataCollection->isEmpty()) {
            throw new Exception('create_whatsapp_sending_missing_wautomation_logs');
        }

        try {
            DB::beginTransaction();

            $wapMsgTxt = $this->whatsAppSendingMessageTextService->findOrCreate($dto->chatMessage);
            $wapSendingData = [
                'is_massive' => false,
                'is_automation' => true,
                'user_id' => $dto->user->id,
                'client_id' => $dto->client->id,
                'is_proposal' => $dto->isProposal,
                'send_date' => $dto->sendDate ?? now(),
                'type' => WhatsAppSending::WAP_SENDER_JOB_TYPE,
                'whatsapp_attachment_id' => $dto->attachment?->id,
                'whatsapp_sending_message_text_id' => $wapMsgTxt->id,
            ];
            $wapSending = $this->whatsAppSendingRepository->create($wapSendingData);
            $wapSendingMsgs = $this->whatsAppSendingMessageService->createMultipleWAPSenderJobWAutomationMessages(
                $wapSending, $dto
            );

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $wapSending;
    }


    
    public function markSendingMessagesAsDispatched(WhatsAppSending $wapSending): WhatsAppSending
    {
        $this->whatsAppSendingMessageService->markMultipleAsDispatched($wapSending->whatsAppSendingMessages);
        return $wapSending;
    }


    public function cancel(WhatsAppSending $wapSending): WhatsAppSending
    {
        $this->failIfCanNotBeCancelled($wapSending);

        try {
            DB::beginTransaction();

            $cancelledWapSending = $this->whatsAppSendingRepository->cancel($wapSending);
            $this->whatsAppSendingMessageService->cancelMultiple($wapSending->whatsAppSendingMessages);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        return $cancelledWapSending;
    }


    public function pause(WhatsAppSending $wapSending, ?string $pauseReason = null): WhatsAppSending
    {
        $this->failIfCanNotBePaused($wapSending);

        try {
            DB::beginTransaction();

            $pausedWapSending = $this->whatsAppSendingRepository->pause($wapSending, $pauseReason);
            $this->whatsAppSendingMessageService->pauseMultiple($wapSending->whatsAppSendingMessages);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        return $pausedWapSending;
    }


    public function resume(WhatsAppSending $wapSending): WhatsAppSending
    {
        $this->failIfCanNotBeResumed($wapSending);

        try {
            DB::beginTransaction();

            $resumedWapSending = $this->whatsAppSendingRepository->resume($wapSending);
            $this->whatsAppSendingMessageService->resumeMultiple($wapSending->whatsAppSendingMessages);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        return $resumedWapSending;
    }


    public function finish(WhatsAppSending $wapSending): WhatsAppSending
    {
        $this->failIfCanNotBeFinished($wapSending);
        $finishedWapSending = $this->whatsAppSendingRepository->finish($wapSending);
        return $finishedWapSending;
    }


    public function finishIfApplicable(WhatsAppSending $wapSending): WhatsAppSending
    {
        $notSentMsgsCount = $wapSending->notSentWhatsAppSendingMessagesCount;
        if ($notSentMsgsCount == 0) {
            $wapSending = $this->finish($wapSending);
        }
        return $wapSending;
    }


    public function setFirstAndLastSentMessageDate(WhatsAppSendingMessage $wapMsg): WhatsAppSending
    {
        $wapSending = $wapMsg->whatsAppSending;
        $attrs = [];
        if (!$wapSending->first_sent_message_date) {
            $attrs['first_sent_message_date'] = new DateTime('now');
        }
        $attrs['last_sent_message_date'] = new DateTime('now');

        $wapSending = $this->whatsAppSendingRepository->update($wapSending, $attrs, ['isRefreshEnabled' => false]);
        return $wapSending;
    }


    public function getDailyUsedSendingQuotaByUserAndType(User $user, string $type): int
    {
        $utcTz = new DateTimeZone('UTC');
        $clientTz = new DateTimeZone($user->client->timezone);
        $dateStart = (new DateTime('now'))->setTimezone($clientTz)->setTime(0, 0, 0)->setTimezone($utcTz);
        $dateEnd = (new DateTime('now'))->setTimezone($clientTz)->setTime(23, 59, 59)->setTimezone($utcTz);
        $dailyUsedQuota = $this->whatsAppSendingMessageService->countPeriodSentOrScheduledByUserAndType(
            $user, $type, $dateStart, $dateEnd
        );
        return $dailyUsedQuota;
    }


    protected function failIfCanNotBePaused(WhatsAppSending $wapSending): void
    {
        if (!$wapSending->canBePaused()) {
            if ($wapSending->cancelled_date) {
                throw new Exception('whatsapp_sending_can_not_be_paused_because_is_already_cancelled');
            }
            if ($wapSending->finished_date) {
                throw new Exception('whatsapp_sending_can_not_be_paused_because_is_already_finished');
            }
            if (!$wapSending->paused_date) {
                throw new Exception('whatsapp_sending_can_not_be_paused_because_is_already_paused');
            }
        }
    }


    protected function failIfCanNotBeResumed(WhatsAppSending $wapSending): void
    {
        if (!$wapSending->canBeResumed()) {
            if ($wapSending->cancelled_date) {
                throw new Exception('whatsapp_sending_can_not_be_resumed_because_is_already_cancelled');
            }
            if ($wapSending->finished_date) {
                throw new Exception('whatsapp_sending_can_not_be_resumed_because_is_already_finished');
            }
            if (!$wapSending->paused_date) {
                throw new Exception('whatsapp_sending_can_not_be_resumed_because_is_already_running');
            }
        }
    }


    protected function failIfCanNotBeCancelled(WhatsAppSending $wapSending): void
    {
        if (!$wapSending->canBeCancelled()) {
            if ($wapSending->cancelled_date) {
                throw new Exception('whatsapp_sending_can_not_be_cancelled_because_is_already_cancelled');
            }
            if ($wapSending->finished_date) {
                throw new Exception('whatsapp_sending_can_not_be_cancelled_because_is_already_finished');
            }
        }
    }


    protected function failIfCanNotBeFinished(WhatsAppSending $wapSending): void
    {
        if (!$wapSending->canBeFinished()) {
            if ($wapSending->cancelled_date) {
                throw new Exception('whatsapp_sending_can_not_be_finished_because_is_already_cancelled');
            }
            // Chau, esto lo saco para que no genere conflictos
            /*
            if ($wapSending->finished_date) {
                // Esto se ignora en app/Exceptions/Handler.
                // Ahora que existe el reintento en SendWAutomationWAPIMessageJob, puede suceder frecuentemente.
                throw new Exception('whatsapp_sending_can_not_be_finished_because_is_already_finished');
            }
            */
            if ($wapSending->paused_date) {
                throw new Exception('whatsapp_sending_can_not_be_finished_because_is_paused');
            }
        }
    }


    protected function getFilterCriteriasByName(array $filters): array
    {
        $criterias = [
            'lead_id' => LeadIdCriteria::class,
            'is_massive' => IsMassiveCriteria::class,
            'send_date_end' => SendDateEndCriteria::class,
            'is_automation' => IsAutomationCriteria::class,
            'send_status' => SendStatusFilterCriteria::class,
            'send_date_start' => SendDateStartCriteria::class,
        ];

        $nfilters = [];
        foreach ($filters as $key => $value) {
            if (!$value) {
                continue;
            }
            if (in_array($key, array_keys($criterias)) && $value !== null) {
                $nfilters[$key] = new $criterias[$key]($value);
            } else {
                $nfilters[$key] =  $value;
            }
        }
        return $nfilters;
    }


    private function getSortCriteriasByName($sortsName)
    {
        $sortTypes = [
            'date_asc' => new SortBySendDate('asc'),
            'date_desc' => new SortBySendDate('desc'),
        ];
        return $sortsName ? $sortTypes[$sortsName] : $sortsName;
    }

}
