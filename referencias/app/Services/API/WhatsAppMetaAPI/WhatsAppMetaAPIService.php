<?php

namespace App\Services\API\WhatsAppMetaAPI;

use DateTime;
use Throwable;
use Exception;
use App\Models\User;
use App\Models\Client;
use App\Models\WhatsAppSending;
use App\Models\LeadContactPhone;
use App\Models\WhatsAppTemplate;
use App\Helpers\FacebookAdHelper;
use App\Models\WhatsAppAttachment;
use Illuminate\Support\Collection;
use App\Models\ClientFacebookPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\WhatsAppSendingMessage;
use App\Helpers\WhatsAppVariablesHelper;
use App\Helpers\WhatsAppAttachmentHelper;
use App\Models\WhatsAppMetaAPIConnection;
use App\Services\Traits\GetUserFromRequest;
use App\Services\API\WhatsAppSendingService;
use App\Services\API\ProposalInfoTmpService;
use App\Services\API\LeadContactPhoneService;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\WhatsAppSendingMessageService;
use App\Helpers\WhatsAppMetaAPI\WhatsAppMetaAPIHelper;
use App\DTO\FacebookPage\ClientFacebookPageLeadInfoDTO;
use App\DTO\WAPI\WAPINewWAutomationSendingParametersDTO;
use Facebook\Exceptions\FacebookAuthenticationException;
use App\DTO\WhatsAppMetaAPI\WhatsAppMetaAPIMessageModalDTO;
use App\Services\API\Dispatchers\WhatsAppEventsDispatcherService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\DTO\WhatsAppMetaAPI\WhatsAppMetaAPIPopulatedConnectionDTO;
use App\DTO\WhatsAppMetaAPI\WhatsAppMetaAPINewSendingParametersDTO;
use App\Repositories\Cache\WhatsAppMetaAPIConnectionRepositoryCache;
use App\Repositories\WhatsAppMetaAPI\WhatsAppMetaAPIConnectionRepository;


class WhatsAppMetaAPIService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $redirectUrl;


    public function __construct(
        protected readonly WhatsAppMetaAPIHelper $whatsAppMetaAPIHelper,
        protected readonly WhatsAppSendingService $whatsAppSendingService,
        protected readonly ProposalInfoTmpService $proposalInfoTmpService,
        protected readonly LeadContactPhoneService $leadContactPhoneService,
        protected readonly WhatsAppSendingMessageService $whatsAppSendingMessageService,
        protected readonly TimelineEventsDispatcherService $timelineEventsDispatcherService,
        protected readonly WhatsAppEventsDispatcherService $whatsAppEventsDispatcherService,
        protected readonly WhatsAppMetaAPIConnectionRepository |
        WhatsAppMetaAPIConnectionRepositoryCache $whatsAppMetaAPIConnectionRepository,
    ) {
        $this->redirectUrl = 'https://{subdomain}.clienty.co/configurations/whatsapp-meta-api';
    }


    public function findWhatsAppMetaAPIConnectionById(int $id): ?WhatsAppMetaAPIConnection
    {
        return $this->whatsAppMetaAPIConnectionRepository->findById($id);
    }


    public function findLastConnectionByUser(User $user): ?WhatsAppMetaAPIConnection
    {
        return $this->whatsAppMetaAPIConnectionRepository->findLastByUser($user);
    }


    public function findClientOtherWABAIdConnections(WhatsAppMetaAPIConnection $whatsAppMetaAPIConnection): Collection
    {
        return $this->whatsAppMetaAPIConnectionRepository->findClientOtherWABAIdConnections(
            $whatsAppMetaAPIConnection
        );
    }


    public function findActiveConnection(Client $client, string $phoneNumberId): ?WhatsAppMetaAPIConnection
    {
        return $this->whatsAppMetaAPIConnectionRepository->findActiveConnection($client, $phoneNumberId);
    }


    public function findActiveByPhoneNumberId(string $phoneNumberId): ?WhatsAppMetaAPIConnection
    {
        return $this->whatsAppMetaAPIConnectionRepository->findActiveByPhoneNumberId($phoneNumberId);
    }


    public function findActiveByPhoneNumber(string $phoneNumber): ?WhatsAppMetaAPIConnection
    {
        return $this->whatsAppMetaAPIConnectionRepository->findActiveByPhoneNumber($phoneNumber);
    }


    public function findConnectionsByClient(Client $client, array $opts = []): Collection
    {
        return $this->whatsAppMetaAPIConnectionRepository->findAllByClient($client, $opts);
    }


    public function findPopulatedLastConnectionDTOByUser(User $user): ?WhatsAppMetaAPIPopulatedConnectionDTO
    {
        $wapMetaConn = $this->findLastConnectionByUser($user); // (Model) WhatsAppMetaAPIConnection
        if (!$wapMetaConn) {
            return null;
        }

        $accessToken = $wapMetaConn->access_token;
        $dto = new WhatsAppMetaAPIPopulatedConnectionDTO($wapMetaConn);
        
        if (!$dto->modelHasAssociatedMetaWABA()) {
            $wabaIds = $this->whatsAppMetaAPIHelper->extractWabaIdsFromToken($accessToken);
            foreach ($wabaIds as $wabaId) {
                $wabaInfo = $this->whatsAppMetaAPIHelper->getWABAInfoById($wabaId, $accessToken);
                $phoneNumbers = $this->whatsAppMetaAPIHelper->getPhoneNumbers($wabaId, $accessToken);
                $dto->appendEnabledMetaAPIData($wabaInfo, $phoneNumbers);
            }
            return $dto;
        }

        try {
            $dto->associatedMetaWABAData = $this->whatsAppMetaAPIHelper->getWABAInfoById(
                $wapMetaConn->waba_id, $accessToken
            );
            $dto->associatedMetaWABAData['isWabaSubscribedToWebhooks'] = $this->whatsAppMetaAPIHelper
                ->isWabaSubscribedToWebhooks($wapMetaConn->waba_id, $accessToken)
            ;
            $dto->associatedMetaPhoneNumberData = $this->whatsAppMetaAPIHelper->getPhoneNumberInfoById(
                $wapMetaConn->phone_number_id, $accessToken
            );
            $messagingLimitTierStr = $this->whatsAppMetaAPIHelper->getMessagingLimitByWabaId(
                $wapMetaConn->waba_id, $accessToken
            );
            $dto->associatedMetaPhoneNumberData['whatsapp_business_manager_messaging_limit'] = $messagingLimitTierStr;
        } catch (Exception $e) {
            $dto->metaError = $e->getMessage();
        }
        return $dto;
    }


    public function getOAuthRedirectUrl(User $user): string
    {
        $state = [
            'user_id' => $user->id,
            'client_id' => $user->client->id,
            'user_username' => $user->username,
            'client_subdomain' => $user->client->subdomain,
        ];
        return $this->whatsAppMetaAPIHelper->getOAuthRedirectUrl($state);
    }

    
    public function validateWebhook(array $data): string
    {
        $mode = $data['hub_mode'];
        $token = $data['hub_verify_token'];
        $challenge = $data['hub_challenge'];
        return $this->whatsAppMetaAPIHelper->validateWebhook($mode, $token, $challenge);
    }


    public function exchangeCodeForAccessToken(string $code): string
    {
        return $this->whatsAppMetaAPIHelper->exchangeCodeForAccessToken($code);
    }


    public function createOrUpdateNewUserConnection(User $user, string $accessToken): WhatsAppMetaAPIConnection
    {
        $data = [
            'access_token' => $accessToken,
            'access_token_last_generation_date' => new DateTime(),
            'access_token_expiration_date' => (new DateTime())->modify('+60 days'),
        ];
        $existentConnection = $this->whatsAppMetaAPIConnectionRepository->findLastByUser($user);
        if ($existentConnection) {
            return $this->whatsAppMetaAPIConnectionRepository->update($existentConnection, $data);
        }

        $data['user_id'] = $user->id;
        $data['client_id'] = $user->client_id;
        return $this->whatsAppMetaAPIConnectionRepository->create($data);
    }


    public function cloneConnection(
        WhatsAppMetaAPIConnection $sourceConnection,
        User $targetUser
    ): WhatsAppMetaAPIConnection {
        return $this->whatsAppMetaAPIConnectionRepository->cloneConnectionForUser(
            sourceConnection:$sourceConnection, targetUser: $targetUser
        );
    }


    public function deleteUserConnection(User $user): ?WhatsAppMetaAPIConnection
    {
        $connection = $this->whatsAppMetaAPIConnectionRepository->findLastByUser($user);
        if (!$connection) {
            return null;
        }
        return $this->whatsAppMetaAPIConnectionRepository->delete($connection);
    }


    public function susbcribeWABAToWebhooks(
        WhatsAppMetaAPIConnection $whatsAppMetaConnection,
        string $metaWABAId
    ): bool {
        $accessToken = $whatsAppMetaConnection->access_token;
        $isSubscribed = $this->whatsAppMetaAPIHelper->subscribeWabaToWebhooks($metaWABAId, $accessToken);
        return $isSubscribed;
    }


    public function associatePhoneNumberToConnection(
        WhatsAppMetaAPIConnection $whatsAppMetaConnection,
        string $metaWABAId,
        string $metaPhoneNumberId,
    ): WhatsAppMetaAPIConnection {
        $accessToken = $whatsAppMetaConnection->access_token;
        $wabaInfo = $this->whatsAppMetaAPIHelper->getWABAInfoById($metaWABAId, $accessToken);
        $phoneInfo = $this->whatsAppMetaAPIHelper->getPhoneNumberInfoById($metaPhoneNumberId, $accessToken);

        if (empty($phoneInfo['display_phone_number'])) {
            throw new Exception("Meta no devolvió display_phone_number para phone_id {$metaPhoneNumberId}");
        }

        $phoneVerifiedName = $phoneInfo['verified_name'] ?? $phoneInfo['display_phone_number'];
        $wabaName = $wabaInfo['name'] ?? $whatsAppMetaConnection->client?->name ?? $phoneInfo['display_phone_number'];
        $phoneOnlyNumbers = preg_replace('/[^0-9]/', '', $phoneInfo['display_phone_number']);
        $data = [
            'waba_id' => $metaWABAId,
            'waba_name' => $wabaName,
            'phone_number' => $phoneOnlyNumbers,
            'phone_number_id' => $metaPhoneNumberId,
            'phone_number_verified_name' => $phoneVerifiedName,
        ];
        $updatedConn = $this->whatsAppMetaAPIConnectionRepository->update($whatsAppMetaConnection, $data);
        return $updatedConn;
    }
    

    public function listTemplates(WhatsAppMetaAPIConnection $whatsAppMetaConnection): Collection
    {
        $wabaId = $whatsAppMetaConnection->waba_id;
        $accessToken = $whatsAppMetaConnection->access_token;
        $wapMetaTemplates = $this->whatsAppMetaAPIHelper->getMessageTemplates($wabaId, $accessToken);
        return $wapMetaTemplates;
    }


    public function getMessageModalInfo(Collection $leadIds): WhatsAppMetaAPIMessageModalDTO
    {
        $user = $this->getUser();
        $client = $this->getClient();
        $leadContactPhones = $this->leadContactPhoneService->findByClientAndLeadIds($client, $leadIds);

        $dto = new WhatsAppMetaAPIMessageModalDTO();
        $dto->leadContactPhones = $leadContactPhones;
        $dto->whatsAppMetaAPIConnection = $user->whatsAppMetaAPIConnection;
        if (!$dto->whatsAppMetaAPIConnection) {
            $dto->metaError = 'whatsapp_meta_api_connection_does_not_exist';
            $dto->associatedMetaPhoneNumberData = null;
            return $dto;
        }

        try {
            $accessToken = $user->whatsAppMetaAPIConnection->access_token;
            $phoneNumberId = $user->whatsAppMetaAPIConnection->phone_number_id;
            $dto->associatedMetaPhoneNumberData = $this->whatsAppMetaAPIHelper->getPhoneNumberInfoById(
                $phoneNumberId, $accessToken
            );
        } catch (\Exception $e) {
            $dto->metaError = $e?->getMessage();
            $dto->associatedMetaPhoneNumberData = null;
        }
        return $dto;
    }


    public function createNewSending(
        WhatsAppTemplate $whatsAppTemplate,
        WhatsAppMetaAPINewSendingParametersDTO $dto
    ): WhatsAppSending {
        $this->validateWhatsAppMetaAPIIsEnabled($this->getClient(), $this->getUser());
        $whatsAppSending = $this->whatsAppSendingService->createNewWhatsAppMetaAPISending($whatsAppTemplate, $dto);
        
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


    public function createNewOpenMessageSending(
        WhatsAppMetaAPINewSendingParametersDTO $dto
    ): WhatsAppSending {
        $this->validateWhatsAppMetaAPIIsEnabled($this->getClient(), $this->getUser());
        $whatsAppSending = $this->whatsAppSendingService->createNewWhatsAppMetaAPIOpenSending($dto);

        if ($whatsAppSending->is_proposal) {
            $proposalInfoTmp = $this->proposalInfoTmpService->createNewByWAPSendingAndDTO($whatsAppSending, $dto);
        }

        $this->dispatchWhatsAppSendingMessages($whatsAppSending);
        return $whatsAppSending;
    }


    public function sendOpenTextMessage(WhatsAppSendingMessage $wapSendingMsg): array
    {
        $redirectWapi = config('wapi.redirect_wapi', false);
        $redirectWapiToPhone = config('wapi.redirect_wapi_to_phone', null);
        $toPhoneNumber = $wapSendingMsg->phone_number;
        if ($redirectWapi && $redirectWapiToPhone) {
            $toPhoneNumber = $redirectWapiToPhone;
        }

        try {
            $this->validateWhatsAppMetaAPIIsEnabled($wapSendingMsg->client, $wapSendingMsg->user);

            $whatsAppMetaAPIConnection = $wapSendingMsg->user->whatsAppMetaAPIConnection;
            $wapSendingMessageText = $wapSendingMsg->whatsAppSending->whatsAppSendingMessageText;
            $messageTextJson = json_decode($wapSendingMessageText->message, true);
            $messageText = $messageTextJson['body'] ?? $wapSendingMessageText->message;

            $metaResponse = $this->whatsAppMetaAPIHelper->sendTextMessage(
                $whatsAppMetaAPIConnection, $toPhoneNumber, $messageText
            );
            $this->whatsAppSendingService->markWhatsAppMetaAPIMessageAsSent($wapSendingMsg, $metaResponse, true);
        } catch (Throwable $e) {
            $this->whatsAppSendingService->markMessageAsSent($wapSendingMsg, false, $e->getMessage());
            throw $e;
        }
        return $metaResponse;
    }


    public function createNewWAutomationSending(
        WhatsAppTemplate $whatsAppTemplate,
        WAPINewWAutomationSendingParametersDTO $dto,
    ): WhatsAppSending {
        $this->validateWhatsAppMetaAPIIsEnabled($dto->client, $dto->user);
        
        try {
            DB::beginTransaction();

            $whatsAppSending = $this->whatsAppSendingService->createNewWhatsAppMetaAPIWautomationSending(
                $whatsAppTemplate, $dto
            );
            $this->whatsAppSendingService->markSendingMessagesAsDispatched($whatsAppSending);
            $this->whatsAppEventsDispatcherService->dispatchSendWAutomationWhatsAppMetaAPIMessagesJobs(
                $whatsAppSending
            );
            
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
            $this->whatsAppEventsDispatcherService->dispatchSendWhatsAppMetaAPIMessagesJobs($whatsAppSending);
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
            $this->whatsAppEventsDispatcherService->dispatchMultipleSendWhatsAppMetaAPIMessagesJobs(
                $whatsAppSendingMessages
            );
            
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    public function sendTemplateMessage(WhatsAppSendingMessage $wapSendingMsg): array
    {
        $redirectWapi = config('wapi.redirect_wapi', false);
        $redirectWapiToPhone = config('wapi.redirect_wapi_to_phone', null);
        $toPhoneNumber = $wapSendingMsg->phone_number;
        if ($redirectWapi && $redirectWapiToPhone) {
            $toPhoneNumber = $redirectWapiToPhone;
        }

        try {
            $this->validateWhatsAppMetaAPIIsEnabled($wapSendingMsg->client, $wapSendingMsg->user);
            
            $wapTemplate = $wapSendingMsg->whatsAppSending->whatsAppTemplate;
            $whatsAppMetaAPIConnection = $wapSendingMsg->user->whatsAppMetaAPIConnection;

            $bodyVariables = $this->buildOrderedVariablesArray(
                $wapTemplate->body, $wapSendingMsg->leadContactPhone, $wapSendingMsg->user
            );
            $headerVariables = $this->buildOrderedVariablesArray(
                $wapTemplate->meta_header_text, $wapSendingMsg->leadContactPhone, $wapSendingMsg->user
            );

            $attachmentData = [];
            $wapAttachment = $wapTemplate->whatsAppAttachment;
            // Si el WhatsAppSending tiene un adjunto propio (por reemplazo manual o automatización),
            // usar ese en lugar del de la plantilla.
            if ($wapSendingMsg->whatsAppSending->whatsAppAttachment) {
                $wapAttachment = $wapSendingMsg->whatsAppSending->whatsAppAttachment;
            }
            if ($wapAttachment) {
                $attachmentData = [
                    'caption' => null,
                    'type' => $wapAttachment->getMetaMediaType(),
                    'filename' => $wapAttachment->original_filename,
                    'link' => resolve(WhatsAppAttachmentHelper::class)->getTemporaryUrl($wapAttachment, 30),
                ];
            }

            $metaResponse = $this->whatsAppMetaAPIHelper->sendTemplateMessage(
                languageCode: 'es_ES',
                toPhoneNumber: $toPhoneNumber,
                bodyVariables: $bodyVariables,
                attachmentData: $attachmentData,
                headerVariables: $headerVariables,
                accessToken: $whatsAppMetaAPIConnection->access_token,
                phoneNumberId: $whatsAppMetaAPIConnection->phone_number_id,
                templateName: $wapSendingMsg->whatsAppSending->whatsAppTemplate->meta_name,
            );

            $this->whatsAppSendingService->markWhatsAppMetaAPIMessageAsSent($wapSendingMsg, $metaResponse, true);
        } catch (Throwable $e) {
            $this->whatsAppSendingService->markMessageAsSent($wapSendingMsg, false, $e->getMessage());
            throw $e;
        }
        return $metaResponse;
    }


    public function setWabaIdToUserWhatsAppTemplates(
        WhatsAppMetaAPIConnection $wapConnection,
        array $opts = [],
    ): void {
        $logger = $opts['logger'] ?? null;
        $logger?->info('-----------------------------------');

        if (!$wapConnection->waba_id) {
            $logger?->info('- wapConnection has no waba_id');
        }

        $wabaId = $wapConnection->waba_id;
        $accessToken = $wapConnection->access_token;
            
        $logger?->info("- wapConnection ID: {$wapConnection->id}");
        $logger?->info("- wapConnection waba_name: {$wapConnection->waba_name}");
        $logger?->info("- WABA ID: {$wabaId}");

        $metaTemplates = $this->whatsAppMetaAPIHelper->getMessageTemplates($wabaId, $accessToken);
        $logger?->info("- Meta Templates count: {$metaTemplates->count()}");

        foreach ($metaTemplates as $metaTemplate) {
            $logger?->info("- Meta Template name: {$metaTemplate->name}");
            $wapTemplates = WhatsAppTemplate::where('client_id', $wapConnection->client_id)
                ->where('meta_id', $metaTemplate->id)
                ->get()
            ;
            $logger?->info("- wapTemplates count: {$wapTemplates->count()}");
            foreach ($wapTemplates as $wapTemplate) {
                if (!$wapTemplate->waba_id) {
                    $wapTemplate->waba_id = $wabaId;
                    $wapTemplate->save();

                    $logger?->info("- wapTemplate ID {$wapTemplate->id} WABA ID SETTED");
                } else {
                    $logger?->info("- wapTemplate ID {$wapTemplate->id} already has WABA ID");
                }

                $logger?->info("---------");
            }
        }
        $logger?->info("\n\n");
    }


    /**
     * Ordena variables named según la aparición en el texto del template.
     * Meta necesita que el array de variables se envíe en orden.
     */
    private function buildOrderedVariablesArray(
        ?string $bodyOrHeaderText,
        LeadContactPhone $leadContactPhone,
        User $user,
    ): array {
        if (!$bodyOrHeaderText) {
            return [];
        }

        // extrae variables en orden
        preg_match_all('/{{\s*([A-Za-z0-9_]+)\s*}}/', $bodyOrHeaderText, $match);
        $orderedVarNames = $match[1] ?? [];
        if (!$orderedVarNames) {
            return [];
        }

        // ['nombre' => 'xxxx', 'varName' => 'value', ...]
        $extractedVars = WhatsAppVariablesHelper::getVariablesArray(
            user: $user,
            fallbackValue: '—',
            chatMessage: $bodyOrHeaderText,
            leadContactPhone: $leadContactPhone,
        );

        // 3) construir lista posicional según aparición (con duplicados)
        $params = [];
        foreach ($orderedVarNames as $varName) {
            if (!array_key_exists($varName, $extractedVars)) {
                throw new Exception("Falta valor para {{{$varName}}} en el template.");
            }
            $params[] = [
                'type' => 'text',
                'parameter_name' => (string) $varName,
                'text' => (string) $extractedVars[$varName]
            ];
        }
        return $params;
    }


    protected function validateWhatsAppMetaAPIIsEnabled(Client $client, User $user): void
    {
        if (!$client->clientSettings->enable_whatsapp_meta_api) {
            throw new Exception('whatsapp_meta_api_is_not_enabled');
        }
        if (!$user->whatsAppMetaAPIConnection) {
            throw new Exception('whatsapp_meta_api_connection_does_not_exist');
        }
    }


    public function subscribe(): Collection
    {
        // $pages = $this->whatsAppMetaAPIHelper->getFacebookSubscribedPages();
        // $clientFacebookPages = new Collection();
        // foreach ($pages as $page) {
        //     // find facebook page
        //     $clientFacebookPage = $this
        //         ->whatsAppMetaAPIConnectionRepository
        //         ->findWithTrashedByClientAndPageId($this->getClient(), $page['id'])
        //     ;
        //     // get page info (name and about)
        //     $info = $this->whatsAppMetaAPIHelper->getFacebookSubscribedPageInfo($page['id'], $page['access_token']);
        //     $data = [
        //         'client_id' => $this->getClient()->id,
        //         'page_id' => $page['id'],
        //         'page_token' => $page['access_token'],
        //         'name' => $info['name'] ?? null,
        //         'about' => $info['about'] ?? null,
        //     ];

        //     // if exists fill and null delete_at
        //     if ($clientFacebookPage) {
        //         $clientFacebookPage->fill($data);
        //         $clientFacebookPage->deleted_at = null;
        //         $clientFacebookPage->save();
        //         $clientFacebookPage->fresh();
        //     } else {
        //         $clientFacebookPage = $this->whatsAppMetaAPIConnectionRepository->insert($this->getClient(), $data);
        //     }
        //     $clientFacebookPages->add($clientFacebookPage);
        // }
        // return $clientFacebookPages;
    }



}
