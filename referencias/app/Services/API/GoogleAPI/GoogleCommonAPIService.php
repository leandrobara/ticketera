<?php

namespace App\Services\API\GoogleAPI;

use Exception;
use Throwable;
use Google_Client;
use App\Models\User;
use App\Models\Lead;
use App\Models\Client;
use Google\Service\Gmail;
use Google\Service\Oauth2;
use App\Helpers\GoogleAPIHelper;
use Google\Service\PeopleService;
use Illuminate\Support\Facades\DB;
use App\Models\GoogleAPIUserToken;
use Illuminate\Support\Collection;
use App\Models\GoogleAPIUserContact;
use Google\Service\PeopleService\Person;
use App\DTO\GoogleAPI\GoogleAPIContactDTO;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\GoogleAPIUserTokenService;
use App\Exceptions\Services\GoogleAPI\InvalidTokenTypeException;
use App\Exceptions\Services\GoogleAPI\InvalidGoogleAPIScopeException;
use App\Exceptions\Services\GoogleAPI\InvalidLinkedEmailAddressException;
use App\Exceptions\Services\GoogleAPI\GoogleAPIGetTokenFromCodeException;
use App\Exceptions\Services\GoogleAPI\UserGoogleAPITokenNotFoundException;
use App\Exceptions\Services\GoogleAPI\MissingClientyIdInContactDTOException;
// use App\Exceptions\Helpers\GoogleAPIHelper\GoogleClientExpiredAccessTokenException; // deprecado


class GoogleCommonAPIService
{

    use GetClientFromRequest;


    private $testAddresses;
    private $googleAPIHelper;
    // private $googleClientsPool = [];
    private $googleAPIUserTokenService;

    const GMAIL_SCOPE = GoogleAPIHelper::GMAIL_SCOPE;
    const PEOPLE_SCOPE = GoogleAPIHelper::PEOPLE_SCOPE;


    public function __construct(
        GoogleAPIHelper $googleAPIHelper,
        GoogleAPIUserTokenService $googleAPIUserTokenService,
        array $testAddresses = []
    ) {
        $this->testAddresses = $testAddresses;
        $this->googleAPIHelper = $googleAPIHelper;
        $this->googleAPIUserTokenService = $googleAPIUserTokenService;
    }


    public function getGoogleAuthUrl(User $user, array $scopes): string
    {

        $opts = [
            'scopes' => $scopes,
            'user_id' => $user->id,
            'client_id' => $user->client_id,
            'client_subdomain' => $user->client->subdomain,
        ];
        if (in_array(self::GMAIL_SCOPE, $scopes)) {
            $url = $this->googleAPIHelper->setGmailCredentials($user)->createAuthUrl($opts);
        } else {
            $url = $this->googleAPIHelper->setPeopleCredentials()->createAuthUrl($opts);
        }
        
        return $url;
    }


    public function getAndStoreAccessTokenFromAuthCode(
        User $user,
        string $tokenType,
        string $authCode
    ): GoogleAPIUserToken {
        $isGmailToken = $tokenType == GoogleAPIUserToken::GMAIL_API_TYPE;
        $isPeopleToken = $tokenType == GoogleAPIUserToken::PEOPLE_API_TYPE;
        if (!$isGmailToken && !$isPeopleToken) {
            throw new InvalidTokenTypeException();
        }

        // Si es la segunda vez que se quiere usar un mismo token (el usuario apretó F5 por ejemplo).
        $existentAPIUserToken = $this->googleAPIUserTokenService->findOneAlreadyExistent($user, $tokenType, $authCode);
        if ($existentAPIUserToken) {
            return $existentAPIUserToken;
        }

        $this->googleAPIHelper->setCredentialsByTokenType($tokenType, $user);
        $tokenArr = $this->googleAPIHelper->getAccessTokenFromAuthCode($authCode);
        if ($tokenArr['error'] ?? null) {
            $msg = 'Google response: ' . $tokenArr['error'] . '. ' . ($tokenArr['error_description'] ?? '');
            throw new GoogleAPIGetTokenFromCodeException($msg);
        }

        $existentAPIUserToken = $isGmailToken ? $user->googleGmailAPIUserToken : $user->googlePeopleAPIUserToken;
        
        try {
            DB::beginTransaction();

            // Borro cualquier otro token que exista de ese usuario.
            if ($existentAPIUserToken) {
                $this->googleAPIUserTokenService->delete($existentAPIUserToken);
            }

            $encodedToken = json_encode($tokenArr);
            $googleAPIUserToken = $this->googleAPIUserTokenService->create([
                'user' => $user,
                'type' => $tokenType,
                'auth_code' => $authCode,
                'client' => $user->client,
                'json_token_string' => $encodedToken,
            ]);
            
            // Valido que el email vinculado sea el mismo email del User.
            $client = $this->getGoogleClientFromGoogleUserToken($googleAPIUserToken);
            $linkedEmailAddr = strtolower($this->googleAPIHelper->getLinkedEmailAddress($client));
            $userEmailAddr = strtolower($user->email);
            $isTestAddress = in_array($linkedEmailAddr, $this->testAddresses);
            if (!$isTestAddress && $linkedEmailAddr != $userEmailAddr) {
                $code = 406;
                $msg = "invalid_linked_email_address: {$linkedEmailAddr}";
                throw new InvalidLinkedEmailAddressException($msg, $code);
            }

            $googleAPIUserToken = $this->googleAPIUserTokenService->update(
                $googleAPIUserToken, ['linked_email' => $linkedEmailAddr]
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        
        return $googleAPIUserToken;
    }


    public function isUserGoogleAPITokenEnabled(?GoogleAPIUserToken $googleAPIUserToken, string $scope): bool
    {
        if (!$this->isValidScope($scope)) {
            return false;
        }

        // $isGmailType = $scope == self::GMAIL_SCOPE;
        // $googleAPIUserToken = $isGmailType ? $user->googleGmailAPIUserToken : $user->googlePeopleAPIUserToken;
        if (!$googleAPIUserToken || !$googleAPIUserToken->json_token_string) {
            return false;
        }
        $enabledTokenScopes = explode(' ', $googleAPIUserToken->decodedToken['scope']);
        if (!in_array($scope, $enabledTokenScopes)) {
            return false;
        }
        // Evito esta validación (por ahora) para no ir dos veces a Google.
        // $googleClient = $this->getGoogleClientFromGoogleUserToken($googleAPIUserToken);
        // return $this->googleAPIHelper->clientHasValidTokenForScope($googleClient, $scope);

        return true;
    }


    public function getGoogleClientFromGoogleUserToken(GoogleAPIUserToken $googleAPIUserToken): Google_Client
    {

        $user = $googleAPIUserToken->user;
        $this->googleAPIHelper->setCredentialsByTokenType($googleAPIUserToken->type, $user);
        $googleClient = $this->googleAPIHelper->getGoogleClient($googleAPIUserToken);
        $accessTokenJsonStr = json_encode($googleClient->getAccessToken());
        
        // Actualizo siempre por las dudas que se haya refrescado el token
        $googleAPIUserToken = $this->googleAPIUserTokenService->update(
            $googleAPIUserToken, ['json_token_string' => $accessTokenJsonStr]
        );

        // try {
        //     $user = $googleAPIUserToken->user;
        //     $this->googleAPIHelper->setCredentialsByTokenType($googleAPIUserToken->type, $user);
        //     $googleClient = $this->googleAPIHelper->getGoogleClient($googleAPIUserToken);
        // } catch (GoogleClientExpiredAccessTokenException $e) {
        //     $newTokenArr = $this->googleAPIHelper->refreshAccessToken($googleAPIUserToken);
        //     $newTokenJsonStr = json_encode($newTokenArr);
        //     $googleAPIUserToken = $this->googleAPIUserTokenService->update(
        //         $googleAPIUserToken, ['json_token_string' => $newTokenJsonStr]
        //     );
        //     $googleClient = $this->googleAPIHelper->getGoogleClient($googleAPIUserToken);
        // }

        return $googleClient;
    }


    public function isValidScope(string $scope): bool
    {
        return in_array($scope, GoogleAPIHelper::VALID_SCOPES);
    }

}