<?php

namespace App\Services\API;

use DateTime;
use Exception;
use Throwable;
use DateTimeZone;
use App\Models\User;
use App\Models\Lead;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Helpers\PhonesHelper;
use App\Models\WhatsAppSending;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\WhatsAppSendingMessage;
use App\Helpers\WhatsAppVariablesHelper;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\WAPI\WAPINewSendingParametersDTO;
use App\Repositories\WhatsAppSendingMessageRepository;
use App\DTO\WAPSender\WAPSenderNewSendingParametersDTO;
use App\DTO\WAPI\WAPINewWAutomationSendingParametersDTO;
use App\DTO\WhatsAppSenderExtension\WhatsAppSenderPhonesMapDTO;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;
use App\DTO\WhatsAppMetaAPI\WhatsAppMetaAPINewSendingParametersDTO;


class WhatsAppSendingMessageService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function __construct(
        protected readonly WhatsAppSendingMessageRepository $whatsAppSendingMessageRepository,
        protected readonly TimelineEventsDispatcherService $timelineEventsDispatcherService,
        protected readonly WhatsAppEventsDispatcherService $whatsAppEventsDispatcherService,
        protected readonly PhonesHelper $phonesHelper,
    ) {
    }


    public function countNonAutomationProposalsByLead(Lead $lead): int
    {
        return $this->whatsAppSendingMessageRepository->countNonAutomationProposalsByLead($lead);
    }


    public function createMultipleWapSenderMessages(
        WhatsAppSending $wapSending,
        WhatsAppSenderPhonesMapDTO $phonesMapDTO,
        array $opts = []
    ): Collection {
        $wapMsgs = new Collection();
        $phonesMapCollection = $phonesMapDTO->phonesMap;

        foreach ($phonesMapCollection as $phoneMapObjDTO) {
            if (!$phoneMapObjDTO->leadId) {
                throw new Exception('whatsapp_sending_message_missing_lead_id');
            }
            if (!$phoneMapObjDTO->phoneNumber) {
                throw new Exception('whatsapp_sending_message_missing_phone_number');
            }
            if (!$phoneMapObjDTO->leadContactPhoneId) {
                throw new Exception('whatsapp_sending_message_missing_lead_contact_phone_id');
            }
            
            $data = [
                'user_id' => $wapSending->user_id,
                'client_id' => $wapSending->client_id,
                'lead_id' => $phoneMapObjDTO->leadId,
                'is_massive' => $wapSending->is_massive,
                'whatsapp_sending_id' => $wapSending->id,
                'type' => WhatsAppSending::WAP_SENDER_TYPE,
                'phone_number' => $phoneMapObjDTO->phoneNumber,
                'lead_contact_phone_id' => $phoneMapObjDTO->leadContactPhoneId,
            ];
            
            if ($opts['hasVariables'] ?? false) {
                $variables = $phoneMapObjDTO->variables ? json_decode(json_encode($phoneMapObjDTO->variables)) : null;
                $data['variables'] = $variables;
            }
            $wapMsg = $this->whatsAppSendingMessageRepository->create($data);
            $wapMsgs->push($wapMsg);
        }

        return $wapMsgs;
    }


    public function createMultipleWAPISendingMessages(
        WhatsAppSending $wapSending,
        WAPINewSendingParametersDTO | WAPINewWAutomationSendingParametersDTO $dto,
        array $opts = []
    ): Collection {
        $isUserSending = ($dto instanceof WAPINewSendingParametersDTO);
        $isWAutomationSending = ($dto instanceof WAPINewWAutomationSendingParametersDTO);

        $wapMsgs = new Collection();
        $chatMessage = $dto->chatMessage;
        $client = $isWAutomationSending ? $dto->client : $this->getClient();
        $iterables = $isWAutomationSending ? $dto->wAutomationWAPSendingDataCollection : $dto->leadContactPhones;

        foreach ($iterables as $element) {
            $wAutomationLog = null;
            $leadContactPhone = $element;

            if ($isWAutomationSending) {
                $wAutWAPISendingIndividualData = $element; // WAPINewWAutomationSendingIndividualData
                $wAutomationLog = $wAutWAPISendingIndividualData->wAutomationLog;
                $leadContactPhone = $wAutWAPISendingIndividualData->leadContactPhone;
            }

            if (!$this->phonesHelper->leadContactPhoneNumberHasValidLength($leadContactPhone, $client)) {
                throw new Exception('wapi_sending_phone_number_' . $leadContactPhone->phone . '_invalid_length');
            }

            $user = $isWAutomationSending ? $dto->user : $this->getUser();
            $variables = WhatsAppVariablesHelper::getVariablesArray($chatMessage, $leadContactPhone, $user);
            $phoneNumber = $this->phonesHelper->getWhatsAppFormattedPhoneFromLeadContactPhone(
                $leadContactPhone, $client
            );
            
            $data = [
                'variables' => $variables,
                'phone_number' => $phoneNumber,
                'is_massive' => $dto->isMassive,
                'is_proposal' => $dto->isProposal,
                'user_id' => $wapSending->user_id,
                'type' => WhatsAppSending::WAPI_TYPE,
                'client_id' => $wapSending->client_id,
                'send_date' => $dto->sendDate ?? now(),
                'lead_id' => $leadContactPhone->lead_id,
                'whatsapp_sending_id' => $wapSending->id,
                'wautomation_log_id' => $wAutomationLog?->id,
                'lead_contact_phone_id' => $leadContactPhone->id,
            ];
            $wapMsg = $this->whatsAppSendingMessageRepository->create($data);
            $wapMsgs->push($wapMsg);
        }
        return $wapMsgs;
    }


    public function createMultipleWhatsAppMetaAPISendingMessages(
        WhatsAppSending $wapSending,
        WhatsAppTemplate $whatsAppTemplate,
        WhatsAppMetaAPINewSendingParametersDTO $dto,
        array $opts = []
    ): Collection {
        $isUserSending = ($dto instanceof WhatsAppMetaAPINewSendingParametersDTO);
        // $isWAutomationSending = ($dto instanceof WhatsAppMetaAPINewWAutomationSendingParametersDTO);
        $isWAutomationSending = false;

        $wapMsgs = new Collection();
        $client = $isWAutomationSending ? $dto->client : $this->getClient();
        $iterables = $isWAutomationSending ? $dto->wAutomationWAPSendingDataCollection : $dto->leadContactPhones;

        foreach ($iterables as $element) {
            $wAutomationLog = null;
            $leadContactPhone = $element;

            if ($isWAutomationSending) {
                $wAutWAPISendingIndividualData = $element; // WAPINewWAutomationSendingIndividualData
                $wAutomationLog = $wAutWAPISendingIndividualData->wAutomationLog;
                $leadContactPhone = $wAutWAPISendingIndividualData->leadContactPhone;
            }

            if (!$this->phonesHelper->leadContactPhoneNumberHasValidLength($leadContactPhone, $client)) {
                throw new Exception('whatsapp_sending_phone_number_' . $leadContactPhone->phone . '_invalid_length');
            }

            $user = $isWAutomationSending ? $dto->user : $this->getUser();
            $msgTextsJsonStr = $whatsAppTemplate->getMetaCompleteTextJson();
            $variables = WhatsAppVariablesHelper::getVariablesArray($msgTextsJsonStr, $leadContactPhone, $user);
            $phoneNumber = $this->phonesHelper->getWhatsAppFormattedPhoneFromLeadContactPhone(
                $leadContactPhone, $client
            );
            
            $data = [
                'variables' => $variables,
                'phone_number' => $phoneNumber,
                'is_massive' => $dto->isMassive,
                'is_proposal' => $dto->isProposal,
                'user_id' => $wapSending->user_id,
                'client_id' => $wapSending->client_id,
                'send_date' => $dto->sendDate ?? now(),
                'lead_id' => $leadContactPhone->lead_id,
                'whatsapp_sending_id' => $wapSending->id,
                'wautomation_log_id' => $wAutomationLog?->id,
                'lead_contact_phone_id' => $leadContactPhone->id,
                'type' => WhatsAppSending::WHATSAPP_META_API_TYPE,
            ];
            $wapMsg = $this->whatsAppSendingMessageRepository->create($data);
            $wapMsgs->push($wapMsg);
        }
        return $wapMsgs;
    }


    public function createMultipleWhatsAppMetaAPIOpenSendingMessages(
        WhatsAppSending $wapSending,
        WhatsAppMetaAPINewSendingParametersDTO $dto,
    ): Collection {
        $wapMsgs = new Collection();
        $client = $this->getClient();

        foreach ($dto->leadContactPhones as $leadContactPhone) {
            if (!$this->phonesHelper->leadContactPhoneNumberHasValidLength($leadContactPhone, $client)) {
                throw new Exception('whatsapp_sending_phone_number_' . $leadContactPhone->phone . '_invalid_length');
            }

            $phoneNumber = $this->phonesHelper->getWhatsAppFormattedPhoneFromLeadContactPhone(
                $leadContactPhone, $client
            );

            $data = [
                'variables' => [],
                'phone_number' => $phoneNumber,
                'is_massive' => false,
                'is_proposal' => $dto->isProposal,
                'user_id' => $wapSending->user_id,
                'client_id' => $wapSending->client_id,
                'send_date' => now(),
                'lead_id' => $leadContactPhone->lead_id,
                'whatsapp_sending_id' => $wapSending->id,
                'wautomation_log_id' => null,
                'lead_contact_phone_id' => $leadContactPhone->id,
                'type' => WhatsAppSending::WHATSAPP_META_API_TYPE,
            ];
            $wapMsg = $this->whatsAppSendingMessageRepository->create($data);
            $wapMsgs->push($wapMsg);
        }
        return $wapMsgs;
    }


    public function createMultipleWhatsAppMetaAPIWAutomationMessages(
        WhatsAppSending $wapSending,
        WhatsAppTemplate $whatsAppTemplate,
        WAPINewWAutomationSendingParametersDTO $dto,
        array $opts = []
    ): Collection {
        $wapMsgs = new Collection();
        foreach ($dto->wAutomationWAPSendingDataCollection as $wAutWAPSendingIndividualData) {
            $wAutomationLog = $wAutWAPSendingIndividualData->wAutomationLog;
            $leadContactPhone = $wAutWAPSendingIndividualData->leadContactPhone;

            if (!$this->phonesHelper->leadContactPhoneNumberHasValidLength($leadContactPhone, $dto->client)) {
                throw new Exception('wapi_sending_phone_number_' . $leadContactPhone->phone . '_invalid_length');
            }

            $msgTextsJsonStr = $whatsAppTemplate->getMetaCompleteTextJson();
            $variables = WhatsAppVariablesHelper::getVariablesArray($msgTextsJsonStr, $leadContactPhone, $dto->user);
            $phoneNumber = $this->phonesHelper->getWhatsAppFormattedPhoneFromLeadContactPhone(
                $leadContactPhone, $dto->client
            );
            $data = [
                'is_massive' => false,
                'variables' => $variables,
                'phone_number' => $phoneNumber,
                'is_proposal' => $dto->isProposal,
                'user_id' => $wapSending->user_id,
                'client_id' => $wapSending->client_id,
                'send_date' => $dto->sendDate ?? now(),
                'lead_id' => $leadContactPhone->lead_id,
                'whatsapp_sending_id' => $wapSending->id,
                'wautomation_log_id' => $wAutomationLog->id,
                'lead_contact_phone_id' => $leadContactPhone->id,
                'type' => WhatsAppSending::WHATSAPP_META_API_TYPE,
            ];
            $wapMsg = $this->whatsAppSendingMessageRepository->create($data);
            $wapMsgs->push($wapMsg);
        }
        return $wapMsgs;
    }



    public function createMultipleWAPSenderJobSendingMessages(
        WhatsAppSending $wapSending,
        WAPSenderNewSendingParametersDTO $dto,
        array $opts = []
    ): Collection {

        $user = $this->getUser();
        $wapMsgs = new Collection();
        $client = $this->getClient();
        $chatMessage = $dto->chatMessage;

        foreach ($dto->leadContactPhones as $leadContactPhone) {
            if (!$this->phonesHelper->leadContactPhoneNumberHasValidLength($leadContactPhone, $client)) {
                throw new Exception('wapi_sending_phone_number_' . $leadContactPhone->phone . '_invalid_length');
            }

            $variables = WhatsAppVariablesHelper::getVariablesArray($chatMessage, $leadContactPhone, $user);
            $phoneNumber = $this->phonesHelper->getWhatsAppFormattedPhoneFromLeadContactPhone(
                $leadContactPhone, $client
            );
            
            $data = [
                'variables' => $variables,
                'wautomation_log_id' => null,
                'phone_number' => $phoneNumber,
                'is_proposal' => $dto->isProposal,
                'user_id' => $wapSending->user_id,
                'client_id' => $wapSending->client_id,
                'send_date' => $dto->sendDate ?? now(),
                'lead_id' => $leadContactPhone->lead_id,
                'is_massive' => $dto->isMassive ?? false,
                'whatsapp_sending_id' => $wapSending->id,
                'type' => WhatsAppSending::WAP_SENDER_JOB_TYPE,
                'lead_contact_phone_id' => $leadContactPhone->id,
            ];
            $wapMsg = $this->whatsAppSendingMessageRepository->create($data);
            $wapMsgs->push($wapMsg);
        }
        return $wapMsgs;
    }


    public function createMultipleWAPSenderJobWAutomationMessages(
        WhatsAppSending $wapSending,
        WAPINewWAutomationSendingParametersDTO $dto,
        array $opts = []
    ): Collection {
        $wapMsgs = new Collection();
        foreach ($dto->wAutomationWAPSendingDataCollection as $wAutWAPSendingIndividualData) {
            $wAutomationLog = $wAutWAPSendingIndividualData->wAutomationLog;
            $leadContactPhone = $wAutWAPSendingIndividualData->leadContactPhone;

            if (!$this->phonesHelper->leadContactPhoneNumberHasValidLength($leadContactPhone, $dto->client)) {
                throw new Exception('wapi_sending_phone_number_' . $leadContactPhone->phone . '_invalid_length');
            }

            $variables = WhatsAppVariablesHelper::getVariablesArray($dto->chatMessage, $leadContactPhone, $dto->user);
            $phoneNumber = $this->phonesHelper->getWhatsAppFormattedPhoneFromLeadContactPhone(
                $leadContactPhone, $dto->client
            );
            $data = [
                'is_massive' => false,
                'variables' => $variables,
                'phone_number' => $phoneNumber,
                'is_proposal' => $dto->isProposal,
                'user_id' => $wapSending->user_id,
                'client_id' => $wapSending->client_id,
                'send_date' => $dto->sendDate ?? now(),
                'lead_id' => $leadContactPhone->lead_id,
                'whatsapp_sending_id' => $wapSending->id,
                'wautomation_log_id' => $wAutomationLog->id,
                'type' => WhatsAppSending::WAP_SENDER_JOB_TYPE,
                'lead_contact_phone_id' => $leadContactPhone->id,
            ];
            $wapMsg = $this->whatsAppSendingMessageRepository->create($data);
            $wapMsgs->push($wapMsg);
        }
        return $wapMsgs;
    }


    public function cancelMultiple(Collection $wapMsgs): bool
    {
        return $this->whatsAppSendingMessageRepository->cancelMultiple($wapMsgs);
    }


    public function pauseMultiple(Collection $wapMsgs): bool
    {
        return $this->whatsAppSendingMessageRepository->pauseMultiple($wapMsgs);
    }


    public function resumeMultiple(Collection $wapMsgs): bool
    {
        return $this->whatsAppSendingMessageRepository->resumeMultiple($wapMsgs);
    }


    public function markAsSent(
        WhatsAppSendingMessage $wapMsg,
        bool $success,
        ?string $errorMessage = null
    ): WhatsAppSendingMessage {
        $sendAttemps = ($wapMsg->send_attempts ?? 0) + 1;
        
        if ($errorMessage) {
            $errorMessage = Str::limit($errorMessage, 5000, '...');
        }

        $updatedWapMsg = $this->whatsAppSendingMessageRepository->update(
            $wapMsg,
            [
                'success' => $success,
                'send_attempts' => $sendAttemps,
                'error_message' => $errorMessage,
                'sent_date' => new DateTime('now'),
            ]
        );

        if ($success && !$updatedWapMsg->wautomation_log_id) {
            $this->whatsAppEventsDispatcherService->dispatchApplyWAutomationAfterSendJob($updatedWapMsg);
            if ($updatedWapMsg->is_proposal) {
                $this->whatsAppEventsDispatcherService->dispatchApplyWAutomationProposalModifyLeadAfterSendJob(
                    $updatedWapMsg, $delaySecs = 2
                );
            }
        }
        $this->timelineEventsDispatcherService->whatsAppSendingMessageSent($updatedWapMsg);
        return $updatedWapMsg;
    }


    public function markAsDispatched(WhatsAppSendingMessage $wapMsg): WhatsAppSendingMessage
    {
        $dateNow = new DateTime('now');
        $data = ['dispatched_date' => $dateNow, 'last_dispatched_date' => $dateNow];
        return $this->whatsAppSendingMessageRepository->update($wapMsg, $data);
    }


    public function markAsDispatchedToRetry(WhatsAppSendingMessage $wapMsg): WhatsAppSendingMessage
    {
        $dateNow = new DateTime('now');
        $data = ['last_dispatched_date' => $dateNow];
        return $this->whatsAppSendingMessageRepository->update($wapMsg, $data);
    }


    public function markAsFailed(WhatsAppSendingMessage $wapMsg, string $errorMessage): WhatsAppSendingMessage
    {
        $data = ['success' => false, 'error_message' => $errorMessage];
        return $this->whatsAppSendingMessageRepository->update($wapMsg, $data);
    }


    public function markMultipleAsDispatched(Collection $wapSendingMsgs): bool
    {
        return $this->whatsAppSendingMessageRepository->markMultipleAsDispatched($wapSendingMsgs);
    }


    public function unmarkAsDispatched(WhatsAppSendingMessage $wapMsg): WhatsAppSendingMessage
    {
        $data = ['dispatched_date' => null];
        return $this->whatsAppSendingMessageRepository->update($wapMsg, $data);
    }


    public function countPeriodSentOrScheduledByUserAndType(
        User $user,
        string $type,
        DateTime $dateStart,
        DateTime $dateEnd,
    ): int {
        return $this->whatsAppSendingMessageRepository->countPeriodSentOrScheduledByUserAndType(
            user: $user, type: $type, dateStart: $dateStart, dateEnd: $dateEnd
        );
    }


    public function validateIsEnabledToDispatch(WhatsAppSendingMessage $wapSendingMessage): void
    {
        if (!$wapSendingMessage->lead) {
            throw new Exception('wap_sending_message_has_no_lead');
        }
        if (!$wapSendingMessage->leadContactPhone) {
            throw new Exception('wap_sending_message_has_no_lead_contact_phone');
        }
        if ($wapSendingMessage->sent_date) {
            throw new Exception('wap_sending_message_was_already_sent');
        }
        if ($wapSendingMessage->paused_date) {
            throw new Exception('wap_sending_message_was_paused');
        }
        if ($wapSendingMessage->cancelled_date) {
            throw new Exception('wap_sending_message_was_cancelled');
        }
        if ($wapSendingMessage->dispatched_date) {
            throw new Exception('wap_sending_message_was_already_dispatched');
        }

        if (!$wapSendingMessage->user) {
            throw new Exception('wap_sending_message_has_no_user');
        }

        if ($wapSendingMessage->isWapiType()) {
            if (!$wapSendingMessage->user->wapi_session_phone_number) {
                throw new Exception('wap_sending_message_user_has_no_wapi_session_phone_number');
            }
            if (!$wapSendingMessage->user->wapi_is_synced) {
                throw new Exception('wap_sending_message_user_is_not_synced_with_wapi');
            }
        }

        if ($wapSendingMessage->isWhatsAppMetaAPIType()) {
            // if (!$wapSendingMessage->client->clientSettings->enable_whatsapp_meta_api) {
            //     return new Exception('whatsapp_meta_api_is_not_enabled');
            // }
            // if (!$wapSendingMessage->user->whatsAppMetaAPIConnection) {
            //     return new Exception('whatsapp_meta_api_connection_does_not_exist');
            // }
            // if (!$resendRule->sendWhatsAppTemplate->meta_id) {
            //     return new Exception('whatsapp_template_is_not_a_meta_template');
            // }
            // $wabaMatchingTpl = $this->findWABAMatchingTemplate($resendRule, $wapSendingMessage);
            // if (!$wabaMatchingTpl) {
            //     throw new Exception('whatsapp_template_has_no_match_for_waba_id');
            // }
        }
    }


    public function findStuckedByClient(Client $client): Collection
    {
        return $this->whatsAppSendingMessageRepository->findStuckedByClient($client);
    }


    public function findOneToSaveAsWhatsAppConversationMessage(int $id): ?WhatsAppSendingMessage
    {
        return $this->whatsAppSendingMessageRepository->findOneToSaveAsWhatsAppConversationMessage($id);
    }


    public function findLastOneSentWAPIByUser(User $user): ?WhatsAppSendingMessage
    {
        return $this->whatsAppSendingMessageRepository->findLastOneSentWAPIByUser($user);
    }


    public function findWAPSenderScheduledToSendBetweenSendDates(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd,
        array $opts = [],
    ): Collection {
        return $this->whatsAppSendingMessageRepository->findWAPSenderScheduledToSendBetweenSendDates(
            $client, $dateStart, $dateEnd, $opts
        );
    }


    public function findWhatsAppMetaAPIScheduledToSendBetweenSendDates(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd,
        array $opts = [],
    ): Collection {
        return $this->whatsAppSendingMessageRepository->findWhatsAppMetaAPIScheduledToSendBetweenSendDates(
            $client, $dateStart, $dateEnd, $opts
        );
    }


    public function findFailedWAPSenderScheduledMessagesToRetry(
        User $user,
        array $errorsEnabledToRetry,
        array $opts = []
    ) {
        if (!$user->wap_sender_retry_delay_days) {
            return new Collection();
        }
        $retryMaxDays = $user->wap_sender_retry_delay_days;
        $dateStart = $this->calculateStartDateSkippingWeekends($retryMaxDays);
        return $this->whatsAppSendingMessageRepository->findFailedWAPSenderScheduledMessagesToRetry(
            $user, $dateStart, $errorsEnabledToRetry
        );
    }


    public function findOneByMetaId(string $metaId): ?WhatsAppSendingMessage
    {
        return $this->whatsAppSendingMessageRepository->findOneByMetaId($metaId);
    }


    private function calculateStartDateSkippingWeekends(int $businessDays): DateTime
    {
        $daysToSubtract = 0;
        $businessDaysCount = 0;
        $currentDate = new DateTime();
        while ($businessDaysCount < $businessDays) {
            $daysToSubtract++;
            $checkDate = clone $currentDate;
            $checkDate->modify("-{$daysToSubtract} days");
            if (!$this->dateIsWeekend($checkDate)) {
                $businessDaysCount++;
            }
        }
        $dateStart = (clone $currentDate)->modify("-{$daysToSubtract} days");
        return $dateStart;
    }


    private function dateIsWeekend(DateTime $date): bool
    {
        return ((int) $date->format('w')) == 0 || ((int) $date->format('w')) == 6;
    }

}
