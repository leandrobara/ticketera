<?php

namespace App\Helpers;

use Exception;
use Google_Client;
use App\Models\User;
use Google\Service\Gmail;
use Google\Service\Oauth2;
use Google\Service\PeopleService;
use Illuminate\Support\Collection;
use App\Models\GoogleAPIUserToken;
use Google\Service\Oauth2\Tokeninfo;
use App\DTO\MailerScheduleResponseDTO;
use Google\Service\Exception as GoogleServiceException;
use App\Exceptions\Helpers\GoogleAPIHelper\InvalidTokenTypeException;
use App\Exceptions\Helpers\GoogleAPIHelper\BuildAuthUrlInvalidParams;
use App\Exceptions\Helpers\GoogleAPIHelper\GoogleClientEmptyRefreshTokenException;
use App\Exceptions\Helpers\GoogleAPIHelper\GoogleClientExpiredAccessTokenException;


class GoogleAPIHelper
{

    protected $credentials;
    protected $currentCredentials;

    const GMAIL_SCOPE = Gmail::GMAIL_READONLY;
    const PEOPLE_SCOPE = PeopleService::CONTACTS;
    const USERINFO_EMAIL = PeopleService::USERINFO_EMAIL;
    const VALID_SCOPES = [self::GMAIL_SCOPE, self::PEOPLE_SCOPE];


    // $credentials es un array definido en /config/google_api.php
    public function __construct(array $credentials)
    {
        $this->credentials = $credentials;
        // Default
        $this->setPeopleCredentials();
    }


    public function getGoogleClient(GoogleAPIUserToken $googleAPIUserToken): Google_Client
    {
        $googleClient = $this->getInitializedClient($googleAPIUserToken);
        
        if ($googleClient->isAccessTokenExpired()) {
            $refreshToken = $googleClient->getRefreshToken();
            if (!$refreshToken) {
                throw new GoogleClientEmptyRefreshTokenException();
            }
            $newTokenArr = $googleClient->fetchAccessTokenWithRefreshToken($refreshToken);
            if (isset($newTokenArr['error'])) {
                $msg = 'Google response: ' . $newTokenArr['error'] . '. ' . ($newTokenArr['error_description'] ?? '');
                throw new Exception($msg);
            }
            $googleClient->setAccessToken($newTokenArr);
        }

        // $client = $this->getInitializedClient($googleAPIUserToken);
        // $isExpired = $client->isAccessTokenExpired();
        // if ($isExpired) {
        //     throw new GoogleClientExpiredAccessTokenException();
        // }
        return $googleClient;
    }


    public function clientHasValidTokenForScope(Google_Client $googleClient, string $scope): bool
    {
        try {
            $tokenInfo = $this->getTokenInfo($googleClient);
            $enabledScopes = explode(' ', $tokenInfo->scope);
            if (!in_array($scope, $enabledScopes)) {
                return false;
            }
            return true;
        } catch (GoogleServiceException $e) {
            if ($e->getCode() == 400 && stripos($e->getMessage(), 'Invalid value') !== false) {
                return false;
            }
            throw $e;
        }
        return false;
    }


    public function getLinkedEmailAddress(Google_Client $googleClient): string
    {
        $service = new Oauth2($googleClient);
        $accessToken = $googleClient->getAccessToken()['access_token'];
        $tokenInfo = $service->tokeninfo(['access_token' => $accessToken]);
        return $tokenInfo->email;
    }


    public function getTokenInfo(Google_Client $googleClient): Tokeninfo
    {
        $service = new Oauth2($googleClient);
        $accessToken = $googleClient->getAccessToken()['access_token'];
        return $service->tokeninfo(['access_token' => $accessToken]);
    }


    public function createAuthUrl(array $opts): string
    {
        $userId = $opts['user_id'] ?? null;
        $clientId = $opts['client_id'] ?? null;
        $clientSubdomain = $opts['client_subdomain'] ?? null;
        if (!$clientId || !$userId || !$clientSubdomain) {
            throw new BuildAuthUrlInvalidParams('Empty state params');
        }
        $stateStr = serialize([
            'uid' => $userId,
            'cid' => $clientId,
            'subdomain' => $clientSubdomain,
        ]);

        $scopes = $opts['scopes'] ?? null;
        if (!$scopes || !is_array($scopes)) {
            throw new BuildAuthUrlInvalidParams('Empty scopes');
        }
        foreach ($scopes as $scope) {
            if (!in_array($scope, self::VALID_SCOPES)) {
                throw new BuildAuthUrlInvalidParams("$scope is an invalid scope");
            }
        }
        // Para poder ver la dirección de email del usuario.
        $scopes[] = self::USERINFO_EMAIL;

        $googleClient = new Google_Client();
        $googleClient->setScopes($scopes);
        $googleClient->setState($stateStr);
        $googleClient->setAccessType('offline');
        $googleClient->setApplicationName('Clienty');
        $googleClient->setAuthConfig($this->currentCredentials);
        $googleClient->setPrompt('select_account consent');
        return $googleClient->createAuthUrl();
    }


    public function createPeopleAPIAuthUrl(array $opts): string
    {
        $opts['scopes'] = [self::PEOPLE_SCOPE];
        return $this->createAuthUrl($opts);
    }


    public function getAccessTokenFromAuthCode(string $authCode): array
    {
        $googleClient = new Google_Client();
        $googleClient->setAccessType('offline');
        $googleClient->setApplicationName('Clienty');
        $googleClient->setAuthConfig($this->currentCredentials);
        $googleClient->setPrompt('select_account consent');
        $accessToken = $googleClient->fetchAccessTokenWithAuthCode($authCode);
        return $accessToken;
    }


    public function setCredentialsByTokenType(string $tokenType, User $user): GoogleAPIHelper
    {
        if (!in_array($tokenType, [GoogleAPIUserToken::GMAIL_API_TYPE, GoogleAPIUserToken::PEOPLE_API_TYPE])) {
            throw new \InvalidTokenTypeException();
        }
        // $this->currentCredentials = $this->credentials[$tokenType];
        if ($tokenType == GoogleAPIUserToken::GMAIL_API_TYPE) {
            $this->setGmailCredentials($user);
        }
        if ($tokenType == GoogleAPIUserToken::PEOPLE_API_TYPE) {
            $this->setPeopleCredentials();
        }
        return $this;
    }


    public function setPeopleCredentials(): GoogleAPIHelper
    {
        $this->currentCredentials = $this->credentials[GoogleAPIUserToken::PEOPLE_API_TYPE];
        return $this;
    }


    public function setGmailCredentials(User $user): GoogleAPIHelper
    {
        // Posibles valores de $user->google_gmail_app_name -> 'clienty-gmail-app', 'clienty-gmail-app-2'
        $gmailAppName = $user->google_gmail_app_name;
        $this->currentCredentials = $this->credentials[GoogleAPIUserToken::GMAIL_API_TYPE][$gmailAppName];
        return $this;
    }


    public function getInitializedClient(GoogleAPIUserToken $googleAPIUserToken): Google_Client
    {
        $accessToken = $googleAPIUserToken->decoded_token;
        $client = new Google_Client();
        $client->setAuthConfig($this->currentCredentials);
        $client->setAccessToken($accessToken);
        return $client;
    }

}
