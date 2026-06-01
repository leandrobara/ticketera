<?php

namespace App\Services\API;

use Exception;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Helpers\WAPIHelper;
use App\Repositories\Repository;
use App\Models\AutomationNewLead;
use Illuminate\Support\Collection;
use App\DTO\WAPI\WAPISyncStatusDTO;
use App\Repositories\UserRepository;
use App\Helpers\ClientyMailerAPIHelper;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Services\Traits\StoresExistentInstance;
use App\Services\API\Automations\AutomationLogService;
use App\Services\API\Dispatchers\EmailEventsDispatcherService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\DTO\ClientyConfigurations\UsersSummaryCountAndEventLogsDTO;


class UserService
{

    use GetClientFromRequest, GetUserFromRequest, StoresExistentInstance;

    private $leadService;
    
    // Required setters (not in constructor to avoid circular injection)
    public function setLeadService(LeadService $leadService): UserService
    {
        $this->leadService = $leadService;
        return $this;
    }


    public function __construct(
        private readonly WAPIHelper $WAPIHelper,
        private readonly Repository $userRepository,
        private readonly AutomationLogService $automationLogService,
        private readonly ClientyMailerAPIHelper $clientyMailerAPIHelper,
        private readonly EmailEventsDispatcherService $emailEventsDispatcherService,
        private readonly TimelineEventsDispatcherService $timelineEventsDispatcherService,
    ) {
    }


    public function find(int $id): ?User
    {
        return $this->userRepository->find($id);
    }


    public function findOrFail(int $id): User
    {
        return $this->userRepository->findOrFail($id);
    }


    public function findOneByUserIdAndClientId(int $userId, int $clientId): ?User
    {
        return $this->userRepository->findOneByUserIdAndClientId($userId, $clientId);
    }


    public function findByWAPISessionPhoneNumber(string $wapiSessionPhoneNumber): Collection
    {
        return $this->userRepository->findByWAPISessionPhoneNumber($wapiSessionPhoneNumber);
    }


    public function findAll(): Collection
    {
        return $this->userRepository->findAllByClient($this->getClient());
    }


    public function findAllByClient(Client $client): Collection
    {
        return $this->userRepository->findAllByClient($client);
    }


    public function findAllEnabledByClient(Client $client): Collection
    {
        return $this->userRepository->findAllEnabledByClient($client);
    }


    public function findAllEnabledByClientIds(Collection $clientIds, array $opts = []): Collection
    {
        return $this->userRepository->findAllEnabledByClientIds($clientIds, $opts);
    }


    public function findWithGmailAPIEnabled(): Collection
    {
        return $this->userRepository->findWithGmailAPIEnabled();
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->userRepository->findByClientAndIds($client, $ids);
    }


    public function findByEmailOrUsername($data): ?User
    {
        return $this->userRepository->findByEmailOrUsername($this->getClient(), $data);
    }


    public function findOneByClientAndAPIToken(Client $client, string $apiToken): ?User
    {
        return $this->userRepository->findOneByClientAndAPIToken($client, $apiToken);
    }


    public function update(User $user, array $attributes): User
    {
        return $this->userRepository->update($user, $attributes);
    }


    public function findByClientAndUsernameOrEmail(Client $client, string $usernameOrEmail): ?User
    {
        return User::where('client_id', $client->id)
            ->where(function ($q) use ($usernameOrEmail) {
                $q->where('email', $usernameOrEmail)->orWhere('username', $usernameOrEmail);
            })
            ->first()
        ;
    }


    public function findSuperUser(string $username, string $password): ?User
    {
        return $this->userRepository->findSuperUser($username, $password);
    }


    public function findFirstAdminByClient(Client $client): ?User
    {
        return $this->userRepository->findFirstAdminByClient($client);
    }


    public function enable(User $userToEnable, User $loginUser): User
    {
        $enabledUser = $this->userRepository->update($userToEnable, ['enabled' => true]);

        $this->timelineEventsDispatcherService->userEnabled($enabledUser, $loginUser);
        $this->emailEventsDispatcherService->dispatchSendEnabledUserEmailJob($enabledUser, $loginUser);

        return $enabledUser;
    }


    public function disable(User $userToDisable, User $loginUser): User
    {
        $disabledUser = $this->userRepository->update($userToDisable, ['enabled' => false]);

        $this->timelineEventsDispatcherService->userDisabled($disabledUser, $loginUser);
        $this->emailEventsDispatcherService->dispatchSendDisabledUserEmailJob($disabledUser, $loginUser);

        return $disabledUser;
    }


    public function resetLoginSessions(Collection $users): bool
    {
        return $this->userRepository->resetLoginSessions($users);
    }


    public function create(array $attributes, User $loginUser): User
    {
        $attributes['remember_token'] = Str::uuid();
        $attributes['client_id'] = $this->getClient()->id;
        
        // @todo @facu, quitar hack cuando termine las migraciones WAPI
        if ($this->getClient()->id > 1958) {
            $attributes['wapi_route'] = 'https://wapi3.clienty.co';
        }

        $createdUser = $this->userRepository->create($attributes);
        
        $this->timelineEventsDispatcherService->userCreated($createdUser, $loginUser);
        $this->emailEventsDispatcherService->dispatchSendCreatedUserEmailJob($createdUser, $loginUser);

        return $createdUser;
    }


    public function createNewClientDefault(Client $client, array $attrs): User
    {
        // @todo @facu, quitar hack cuando termine las migraciones WAPI
        if ($client->id > 1958) {
            $attrs['wapi_route'] = 'https://wapi3.clienty.co';
        }
        return $this->userRepository->createNewClientDefault($client, $attrs);
    }


    public function getEmailSign(User $user): ?string
    {
        if (!$user->email_sign_enabled) {
            return null;
        }
        $emailSignHtml = $user->email_sign;
        if (!$emailSignHtml) {
            return null;
        }

        $signSeparator = config('emails.email_sign_separator');
        $signEndFlag = config('emails.email_sign_end_separator_flag');
        $signStartFlag = config('emails.email_sign_start_separator_flag');
        $completeSign = $signStartFlag . $signSeparator . $emailSignHtml . $signEndFlag;
        return $completeSign;
    }


    public function getMyEmailSign(): ?string
    {
        $user = $this->getUser();
        if (!$user) {
            return null;
        }
        $emailSign = $this->getEmailSign($user);
        return $emailSign;
    }


    public function findUserToAssign(Client $client): User
    {
        $enabledUsers = $this->findUsersEnabledToReceiveLeadsByClient($client);
        $lastLead = $this->leadService->findLastLeadByClient($client);

        $enabledUsersCount = $enabledUsers->count();
        if (!$lastLead || $enabledUsersCount === 1) {
            return $enabledUsers->first();
        }

        $lastLeadUserId = $lastLead->user_id;
        foreach ($enabledUsers as $index => $user) {
            if ($user->id === $lastLeadUserId) {
                // If the last-lead user is the last in the list, it returns the first one.
                if (($index + 1) === $enabledUsersCount) {
                    $assignedUser = $enabledUsers->first();
                }
                // Returns the next user in the list.
                $assignedUser = $enabledUsers->get($index + 1);
            }
        }
        // This should never be reached (except last user were disabled to receive).
        return $assignedUser ?? $enabledUsers->first();
    }


    public function findUserToAssignByAutomationNewLead(Client $client, AutomationNewLead $automation): ?User
    {
        $automationUserIds = collect($automation->assign_user_ids ?? []);
        if ($automationUserIds->isEmpty()) {
            return null;
        }

        $enabledUsers = $this->findUsersEnabledToReceiveLeadsByClient($client);
        $automationEnabledUsers = $enabledUsers
            ->filter(function (User $user) use ($automationUserIds) {
                return $automationUserIds->search($user->id) !== false;
            })
            ->sortBy('id')
            ->values()
        ;
        if ($automationEnabledUsers->isEmpty()) {
            return null;
        }

        $lastAutomationLog = $this->automationLogService->findLastOneByAutomationNewLead($automation);
        // Si todavía no se aplicó alguna vez este automation, asigno el primer usuario de la lista.
        if (!$lastAutomationLog) {
            return $automationEnabledUsers->first();
        }

        $lastAssignedUser = $lastAutomationLog->automationNewLeadAssignedUser;
        if (!$lastAssignedUser) {
            return $automationEnabledUsers->first();
        }

        $lastAssignedUserIdIndex = $automationEnabledUsers->search(function (User $user) use ($lastAssignedUser) {
            return $lastAssignedUser->id == $user->id;
        });
        if ($lastAssignedUserIdIndex === false) {
            return $automationEnabledUsers->first();
        }

        $nextUserIdIndex = $lastAssignedUserIdIndex + 1;
        $nextUser = $automationEnabledUsers->get($nextUserIdIndex);
        // Si no me devuelve ninguno, es por que me pasé una posición más allá del último.
        if (!$nextUser) {
            $nextUser = $automationEnabledUsers->first();
        }
        
        return $nextUser;
    }


    public function findUsersEnabledToReceiveLeadsByClient(Client $client): Collection
    {
        $users = $this->findAllByClient($client);
        $enabledUsers = $users->filter(function ($u) {
            return $u->enabled && $u->enabled_to_receive_leads;
        });

        // Fix por las dudas (mejorar)
        if ($enabledUsers->isEmpty()) {
            $enabledUsers = $users->filter(function ($u) {
                return $u->enabled_to_receive_leads;
            });
        }
        // Fix por las dudas (mejorar)
        if ($enabledUsers->isEmpty()) {
            $enabledUsers = $users->filter(function ($u) {
                return $u->enabled;
            });
        }
        // Fix por las dudas (mejorar)
        if ($enabledUsers->isEmpty()) {
            $enabledUsers = $users->filter(function ($u) {
                return true;
            });
        }

        return $enabledUsers->values();
    }


    public function isAWSEmailSynced(?User $user = null): bool
    {
        $user = $user ?? $this->getUser();
        $emailFromAddress = $user->email_from_address;
        if (!$emailFromAddress) {
            return false;
        }
        $isVerified = $this->clientyMailerAPIHelper->emailIsVerified($emailFromAddress);
        return $isVerified;
    }


    public function syncEmailAddressToAWS(
        string $emailAddress,
        ?string $emailFromName = null,
        ?User $user = null
    ): array {
        $user = $user ?? $this->getUser();
        $response = $this->clientyMailerAPIHelper->doEmailVerification($emailAddress);

        // email_is_verified set to true is gonna be made when link email page confirms that user email is verified.
        $attrs = [
            'email_is_verified' => false,
            'email_from_name' => $emailFromName,
            'email_from_address' => $emailAddress,
        ];
        $this->update($user, $attrs);
        return $response;
    }


    public function unsyncUserEmailAddressFromAWS(User $user): bool
    {
        $emailAddress = $user->email_from_address;
        $response = $this->clientyMailerAPIHelper->deleteEmailVerification($emailAddress);

        $attrs = ['email_is_verified' => false, 'email_from_name' => null, 'email_from_address' => null];
        $this->update($user, $attrs);
        return true;
    }


    public function wapiSyncStatus(User $user): WAPISyncStatusDTO
    {
        if (!$user->wapi_session_phone_number) {
            return WAPISyncStatusDTO::buildEmpty();
        }
        $dto = $this->WAPIHelper
            ->setRouteAndEngineFromUser($user)
            ->verifyUserIsSynced($user->wapi_session_phone_number)
        ;
        return $dto;
    }


    public function syncToWapi(User $user, string $wapiSessionPhoneNumber): WAPISyncStatusDTO
    {
        $attrs = ['wapi_session_phone_number' => $wapiSessionPhoneNumber];

        // Si veo que el nro está ya vinculado, lo vinculo con el mismo server de WAPI.
        $otherWapiUsers = $this->findByWAPISessionPhoneNumber($wapiSessionPhoneNumber);
        $otherSyncedWapiUser = $otherWapiUsers->where('wapi_is_synced', true)->first();
        if ($otherSyncedWapiUser && $otherSyncedWapiUser->wapi_route) {
            $attrs['wapi_route'] = $otherSyncedWapiUser->wapi_route;
        }

        $updatedUser = $this->update($user, $attrs);
        $response = $this->WAPIHelper->setRouteAndEngineFromUser($updatedUser)->sync($wapiSessionPhoneNumber);
        return $response;
    }


    public function unsyncFromWAPI(User $user): User
    {
        return $this->update($user, ['wapi_is_synced' => false, 'wapi_session_phone_number' => null]);
    }

}
