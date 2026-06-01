<?php

namespace App\Services\API;

use App\Models\User;
use App\Models\Client;
use App\Helpers\JwtHelper;
use Illuminate\Support\Str;
use App\DTO\SuperUserAuthDTO;
use App\Services\API\UserService;
use App\DTO\MailerSendResponseDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use App\Helpers\ClientyMailerAPIHelper;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\MailerQuickEmailSendRequestParametersDTO;
use App\Exceptions\Services\AuthService\AuthorizationException;


class AuthService
{

    use GetClientFromRequest;


    public function __construct(UserService $userService, string $jwtSecret, string $jwtAlgo)
    {
        $this->jwtAlgo = $jwtAlgo;
        $this->jwtSecret = $jwtSecret;
        $this->userService = $userService;
    }


    public function loginUser(array $data): array
    {
        $client = $this->getClient();

        $superUserAuthDTO = $this->getSuperUserAuth($data);
        $loginUser = $superUserAuthDTO?->loginUser;
        if (!$loginUser) {
            $loginUser = $this->authUser($data); // Throws Exception if fail.
        }

        $expirationDate = $data['expiration_date'];
        $expirationDateTs = $expirationDate->getTimestamp();

        $loginUser->setApiToken();
        $attrs = ['api_token' => $loginUser->api_token, 'api_token_expiration_date' => $expirationDate];
        $loginUser = $this->userService->update($loginUser, $attrs);

        $payload = [
            'exp' => $expirationDateTs,
            'api_token' => $loginUser->api_token,
            'is_super_user' => $superUserAuthDTO ? true : false,
        ];
        $response = [
            'client' => $client,
            'user' => $loginUser,
            'clientSettings' => $client->clientSettings,
            'superUser' => $superUserAuthDTO?->superUser,
            'isSuperUser' => $superUserAuthDTO ? true : false,
            'token' => JwtHelper::encode($payload, $this->jwtSecret, $this->jwtAlgo),
        ];
        // dd([
        //     'user' => $loginUser->toArray(),
        //     'isSuperUser' => $superUserAuthDTO ? true : false,
        //     'superUser' => $superUserAuthDTO?->superUser?->toArray(),
        // ]);
        return $response;
    }


    public function sendResetPasswordEmail(User $user): MailerSendResponseDTO
    {
        $newPassword = Str::random(8);
        $passwordToken = Str::random(8);
        $linkToResetPassword = $this->createLinkToResetPassword($user, $newPassword, $passwordToken);

        $body = view('api.emails.auth.reset-password', [
            'userName' => $user->name,
            'newPassword' => $newPassword,
            'linkToResetPassword' => $linkToResetPassword,
        ])->render();

        $sendParamsDTO = MailerQuickEmailSendRequestParametersDTO::buildFromArray([
            'to' => [$user->email],
            'body' => $body,
            'fromName' => 'Clienty',
            'hasOpenTracking' => false,
            'appCustomId' => 'clienty_reset_password',
            'subject' => 'Clienty: recuperar contraseña',
            'from' => config('emails.leads_notification_from_email'),
        ]);
        
        $userUpdated = $this->userService->update($user, ['reset_password_token' => $passwordToken]);
        $mailerSendResponse = resolve(ClientyMailerAPIHelper::class)->sendQuickEmail($sendParamsDTO);
        return $mailerSendResponse;
    }


    public function loginUserBasicAuth(array $credentials): ?User
    {
        $user = $this->authUser($credentials); // Throws Exception if fail.
        return $user;
    }


    public function logoutUser(User $user)
    {
        $user = $this->userService->update($user, ['api_token' => null, 'api_token_expiration_date' => null]);
        return [];
    }


    private function getSuperUserAuth(array $data): ?SuperUserAuthDTO
    {
        $superUserPassword = $data['password'] ?? null;
        $superUserUsername = $data['username'] ?? null;
        if (!$superUserUsername || !$superUserPassword) {
            return null;
        }

        $isInBehalfOfOther = Str::contains($superUserUsername, '||');
        $loginUsernameOrEmail = $isInBehalfOfOther ? Str::after($superUserUsername, '||') : null;
        $superUserUsername = $isInBehalfOfOther ? Str::before($superUserUsername, '||') : $superUserUsername;
        if (!$superUserUsername) {
            return null;
        }
        if ($isInBehalfOfOther && !$loginUsernameOrEmail) {
            return null;
        }

        $superUser = $this->userService->findSuperUser($superUserUsername, $superUserPassword);
        if (!$superUser) {
            return null;
        }
        if (!password_verify($superUserPassword, $superUser->superuser_password)) {
            return null;
        }

        $client = $this->getClient();
        // Godixital solo puede entrar a clientes propios (y a Clienty para gestionar clientes).
        if ($superUser->isGodixitalUser()) {
            if (!$client->isClienty() && !$client->hasGodixitalContract()) {
                return null;
            }
        }

        
        // Si no se está logueando como otro user.
        if (!$isInBehalfOfOther) {
            // Si se está logueando él mismo. Lo logueo directamente.
            if ($superUser->client_id == $client->id) {
                return new SuperUserAuthDTO($superUser, $superUser);
            }

            // Si el cliente es otro, hago login con el primer user admin.
            $loginUser = $this->userService->findFirstAdminByClient($client);
            if (!$loginUser || $loginUser->client_id != $client->id) {
                return null;
            }
            return new SuperUserAuthDTO($superUser, $loginUser);
        }

        // Si se está logueando como otro user.
        $loginUser = $this->userService->findByClientAndUsernameOrEmail($client, $loginUsernameOrEmail);
        if (!$loginUser || $loginUser->client_id != $client->id) {
            return null;
        }
        return new SuperUserAuthDTO($superUser, $loginUser);
    }


    private function authUser($data): User
    {
        $client = $this->getClient();
        $user = $this->userService->findByClientAndUsernameOrEmail($client, $data['username']);
        
        if (!$user || !password_verify($data['password'], $user->password)) {
            throw new AuthorizationException('invalid_user_or_password');
        }
        if ($user && !$user->enabled) {
            throw new AuthorizationException('user_is_disabled');
        }
        if (!$client->enabled) {
            throw new AuthorizationException('client_is_disabled');
        }
        return $user;
    }


    protected function createLinkToResetPassword(User $user, string $newPassword, string $passwordToken): string
    {
        $querystring = http_build_query([
            't' => $passwordToken,
            'l' => strlen($newPassword),
            'uid' => Crypt::encrypt($user->id),
            'p' => Crypt::encrypt($newPassword),
            'cid' => Crypt::encrypt($user->client_id),
        ]);
        $endpoint = clientUrl($user->client, "/auth/change-password?{$querystring}");
        return $endpoint;
    }

}
