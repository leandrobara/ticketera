<?php

namespace App\Services\API\GoogleAPI;

use DateTime;
use Throwable;
use DateTimeZone;
use App\Models\User;
use App\Models\Lead;
use App\Models\Client;
use Google\Service\Gmail;
use Illuminate\Support\Str;
use App\Helpers\GoogleAPIHelper;
use Google\Service\Gmail\Message;
use App\Models\GoogleAPIUserToken;
use Illuminate\Support\Collection;
use App\Helpers\GoogleGmailAPIHelper;
use App\DTO\GoogleAPI\GoogleAPIContactDTO;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\GoogleAPI\GoogleAPIGmailMessageDTO;
use App\Services\API\GoogleAPI\GoogleCommonAPIService;
use App\Exceptions\Services\GoogleAPI\TokenHasNoPermissionsException;
use App\Exceptions\Services\GoogleAPI\UserGoogleAPITokenNotFoundException;


class GoogleGmailAPIService
{

    use GetClientFromRequest;

    private $googleGmailAPIHelper;
    private $googleCommonAPIService;

    const GMAIL_SCOPE = GoogleAPIHelper::GMAIL_SCOPE;
    const CLIENTY_EMAIL_METADATA_FLAG_END = GoogleGmailAPIHelper::CLIENTY_EMAIL_METADATA_FLAG_END;
    const CLIENTY_EMAIL_METADATA_FLAG_START = GoogleGmailAPIHelper::CLIENTY_EMAIL_METADATA_FLAG_START;
    const CLIENTY_EMAIL_METADATA_FLAG_SEARCH = GoogleGmailAPIHelper::CLIENTY_EMAIL_METADATA_FLAG_SEARCH;


    public function __construct(
        GoogleCommonAPIService $googleCommonAPIService,
        GoogleGmailAPIHelper $googleGmailAPIHelper
    ) {
        $this->googleGmailAPIHelper = $googleGmailAPIHelper;
        $this->googleCommonAPIService = $googleCommonAPIService;
    }


    /**
     * @opt search string Search string or Gmail search query.
     * @opt limit int limit Limit of messages to list. Max value is 500.
     * @opt populated bool If false, it won't populate messages, and it will return basic info.
     * @opt idsToIgnore array<string> Array containing messages ids which are not wanted to be in the list.
     * @opt onlyClientyEmails bool If true, it will return only emails related with Clienty sent emails.
     * @opt minDate DateTime If present, it determines the minimum date email was sent, or discard them.
     *
     * @throws TokenHasNoPermissionsException
     */
    public function listMessages(User $user, array $opts = []): Collection
    {
        $linkedEmailAddr = $user->googleGmailAPIUserToken->linked_email;
        $gmailService = $this->getGmailService($user->googleGmailAPIUserToken);

        $limit = $opts['limit'] ?? 50;
        $search = $opts['search'] ?? null;
        $minDate = $opts['minDate'] ?? null;
        $populated = $opts['populated'] ?? true;
        $idsToIgnore = $opts['idsToIgnore'] ?? [];
        $onlyClientyEmails = $opts['onlyClientyEmails'] ?? true;
        $onlyClientyResponses = $opts['onlyClientyResponses'] ?? true;
        
        $optParams = ['maxResults' => $limit];
        if ($search) {
            $optParams['q'] = $search;
        }
        if ($onlyClientyEmails) {
            $flag = self::CLIENTY_EMAIL_METADATA_FLAG_SEARCH;
            $optParams['q'] = ($search) ? ($search . ' ' . $flag) : $flag;
        }

        try {
            $gmailMessages = $gmailService->users_messages->listUsersMessages('me', $optParams)->getMessages();
        } catch (Throwable $e) {
            $hasNoPermissions = Str::containsAll($e->getMessage(), ['Insufficient Permission', 'PERMISSION_DENIED']);
            if ($hasNoPermissions) {
                throw new TokenHasNoPermissionsException();
            }
            throw $e;
        }

        $gmailMessages = collect($gmailMessages);
        if ($idsToIgnore) {
            $gmailMessages = $gmailMessages->whereNotIn('id', $idsToIgnore);
        }

        
        $dtosCollection = new Collection();
        foreach ($gmailMessages as $gmailMessage) {
            $dtoBuildType = GoogleAPIGmailMessageDTO::BUILD_TYPE_LIGHT;
            if ($populated) {
                $gmailMessage = $this->populateMessage($user, $gmailMessage);
                $dtoBuildType = GoogleAPIGmailMessageDTO::BUILD_TYPE_FULL;
            }

            // Filtro aquellos que NO son respuestas (si son forwarded por ejemplo)
            if ($onlyClientyResponses) {
                $isResponseToUser = $this->googleGmailAPIHelper->isResponseToUser($gmailMessage, $linkedEmailAddr);
                $isResponseFromUser = $this->googleGmailAPIHelper->isResponseFromUser($gmailMessage, $linkedEmailAddr);
                if (!$isResponseToUser && !$isResponseFromUser) {
                    continue;
                }
            }

            try {
                $dtoOpts = ['buildType' => $dtoBuildType, 'linkedEmailAddr' => $linkedEmailAddr];
                $dto = GoogleAPIGmailMessageDTO::buildFromGoogleAPIGmailMessage($gmailMessage, $dtoOpts);
                $dtosCollection->push($dto);
            } catch (Throwable $e) {
                // Si no tiene esta cabecera, lo descarto y sigo.
                if ($e->getMessage() == 'GoogleGmailAPIHelper: not existent email To header') {
                    continue;
                }
                throw $e;
            }
        }

        if ($minDate) {
            $dtosCollection = $dtosCollection->where('sentDate', '>=', $minDate);
        }
        return $dtosCollection;
    }


    public function populateMessage(User $user, Message $basicGmailMessage): Message
    {
        $gmailService = $this->getGmailService($user->googleGmailAPIUserToken);
        $populatedGmailMessage = $gmailService->users_messages->get('me', $basicGmailMessage->id);
        return $populatedGmailMessage;
    }


    public function getGoogleAuthUrl(User $user): string
    {
        $url = $this->googleCommonAPIService->getGoogleAuthUrl($user, [self::GMAIL_SCOPE]);
        return $url;
    }


    public function getAndStoreAccessTokenFromAuthCode(
        User $user,
        string $authCode
    ): GoogleAPIUserToken {
        $tokenType = GoogleAPIUserToken::GMAIL_API_TYPE;
        return $this->googleCommonAPIService->getAndStoreAccessTokenFromAuthCode($user, $tokenType, $authCode);
    }


    public function isAPIEnabled(?GoogleAPIUserToken $googleAPIUserToken): bool
    {
        if (!$googleAPIUserToken) {
            return false;
        }
        return $this->googleCommonAPIService->isUserGoogleAPITokenEnabled($googleAPIUserToken, self::GMAIL_SCOPE);
    }


    protected function getGmailService(?GoogleAPIUserToken $googleGmailAPIUserToken): Gmail
    {
        if (!$googleGmailAPIUserToken) {
            throw new UserGoogleAPITokenNotFoundException();
        }
        $googleClient = $this->googleCommonAPIService->getGoogleClientFromGoogleUserToken($googleGmailAPIUserToken);
        $service = new Gmail($googleClient);
        return $service;
    }


}