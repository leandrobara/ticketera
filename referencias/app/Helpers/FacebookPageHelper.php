<?php

namespace App\Helpers;

use Facebook\Facebook;
use App\Models\ClientFacebookPage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\PersistentData\PersistentDataInterface;
use Facebook\Exceptions\FacebookAuthenticationException;
use App\Exceptions\Helpers\Facebook\FacebookHelperException;


class FacebookPageHelper
{

    private const MODE = 'subscribe';
    private const CHALLENGE = 'godixital';

    private $handleUrl;
    private $fbVersion;
    private $redisHelper;
    private $suscribedFields;
    private $subscriptionScope;
    private $facebookRedirectLoginHelper;


    public function __construct(
        string $fbApp,
        string $fbSecret,
        string $fbVersion,
        string $handleUrl,
        string $suscribedFields,
        array $subscriptionScope,
        RedisHelper $redisHelper,
    ) {
        // Create Facebook Client and get Login Helper
        $this->redisHelper = $redisHelper;
        $this->facebookSDK = new Facebook([
            'app_id' => $fbApp,
            'app_secret' => $fbSecret,
            'default_graph_version' => $fbVersion,
            'persistent_data_handler' => $this->getPersistentDataHandler(),
        ]);
        $this->fbVersion = $fbVersion;
        $this->handleUrl = $handleUrl;
        $this->suscribedFields = $suscribedFields;
        $this->subscriptionScope = $subscriptionScope;
        $this->facebookRedirectLoginHelper = $this->facebookSDK->getRedirectLoginHelper();
    }


    // $state -> uso acá el clientSubdomain para luego saber quien fue
    public function getFacebookLoginUrl($state): string
    {
        // create state (if is an array encode to json)
        $state = base64_encode(is_array($state) ? json_encode($state) : $state);
        $OAuth2Client = $this->facebookSDK->getOAuth2Client();
        $persistentDataHandler = $this->getPersistentDataHandler();
        $persistentDataHandler->set('state', $state);
        $loginUrl = $OAuth2Client->getAuthorizationUrl($this->handleUrl, $state, $this->subscriptionScope);
        return $loginUrl;
    }


    public function getFacebookSubscribedPages(): array
    {
        try {
            $result = [];
            $accessToken = $this->getFacebookAccessToken();
            $longLiveaccessToken = $this->extendFacebookAccessTokenLife($accessToken);
            $user = $this->getFacebookUser($longLiveaccessToken);

            // get page info
            $pages = $this->getFacebookPagesWithAccessToken($user['id'], $accessToken);
            // get page access token
            foreach ($pages as $page) {
                $pageId = $page['id'];
                $accessToken = $page['access_token'];
                // $endpoint = sprintf('/%d/subscribed_apps', $pageId);
                // $response = $this->facebookSDK
                //     ->post($endpoint, ['subscribed_fields' => $this->suscribedFields], $accessToken)
                //     ->getDecodedBody()
                // ;
                $endpoint = "https://graph.facebook.com/{$this->fbVersion}";
                $endpoint .= "/{$pageId}/subscribed_apps?subscribed_fields=leadgen&access_token={$accessToken}";
                $response = Http::withOptions(['verify' => false])->post($endpoint);
                $response = json_decode($response, true);
                if ($response['success'] ?? false) {
                    $result[] = [
                        'id' => $page['id'],
                        'access_token' => $page['access_token'],
                        'user_token' => $longLiveaccessToken->getValue(),
                    ];
                }
            }
        } catch (FacebookResponseException $e) {
            throw new FacebookHelperException('Response Error trying to subscribe page in app ' . $e->getMessage());
        } catch (FacebookSDKException $e) {
            throw new FacebookHelperException('SDK Error trying to subscribe page in app ' . $e->getMessage());
        }
        return $result;
    }


    public function getFacebookSubscribedPageInfo($facebookPageId, $facebookPageToken, $fields = ['name', 'about'])
    {
        $page = $this->facebookSDK
            ->get(sprintf('/%d?fields=%s', $facebookPageId, implode(',', $fields)), $facebookPageToken)
            ->getDecodedBody()
        ;
        return $page;
    }


    public function unsubscribeFacebookPage(ClientFacebookPage $clientFacebookPageModel)
    {
        $pageId = $clientFacebookPageModel->page_id;
        $pageToken = $clientFacebookPageModel->page_token;
        $response = $this->facebookSDK->delete(sprintf('/%d/subscribed_apps', $pageId), [], $pageToken);
        return $response->getDecodedBody();
    }


    public function getFacebookPages($facebookUserId, $accessToken)
    {
        $response = $this->facebookSDK
            ->get(sprintf('/%d/accounts', $facebookUserId), $accessToken->getValue())
            ->getDecodedBody()
        ;
        return $response['data'] ?? [];
    }


    public function validateWebhook(string $mode, string $token, string $challenge): string
    {
        $data = "";
        if ($mode == self::MODE && $token === self::CHALLENGE) {
            $data = $challenge;
        }
        return $data;
    }


    private function getFacebookPagesWithAccessToken($facebookUserId, $accessToken)
    {
        try {
            $pagesWithAccessTokens = [];
            $pages = $this->getFacebookPages($facebookUserId, $accessToken);
            // subscribe all pages selected
            foreach ($pages as $page) {
                // get page access token
                $response = $this->facebookSDK->get(
                    sprintf('/%s?fields=access_token', $page['id']), $accessToken->getValue()
                );
                $pagesWithAccessTokens[] = $response->getDecodedBody();
            }
        } catch (FacebookResponseException $e) {
            throw new FacebookHelperException('Response Error getting page ' . $e->getMessage());
        } catch (FacebookSDKException $e) {
            throw new FacebookHelperException('SDK Error getting page ' . $e->getMessage());
        }
        return $pagesWithAccessTokens;
    }


    private function getFacebookAccessToken()
    {
        try {
            $facebookAccessToken = $this->facebookRedirectLoginHelper->getAccessToken();
        } catch (FacebookResponseException $e) {
            // When Graph returns an error
            throw new FacebookHelperException('Graph returned an error getting user token: ' . $e->getMessage());
        } catch (FacebookSDKException $e) {
            // When validation fails or other local issues
            throw new FacebookHelperException('Facebook SDK returned an error getting user token: ' . $e->getMessage());
        }

        // check if token exists
        if (!isset($facebookAccessToken)) {
            throw new FacebookHelperException(
                'Error: ' . $this->facebookRedirectLoginHelper->getError() . PHP_EOL .
                'Error Code: ' . $this->facebookRedirectLoginHelper->getErrorCode() . PHP_EOL .
                'Error Reason: ' . $this->facebookRedirectLoginHelper->getErrorReason() . PHP_EOL .
                'Error Description: ' . $this->facebookRedirectLoginHelper->getErrorDescription() . PHP_EOL
            );
        }
        return $facebookAccessToken;
    }


    /**
     * Make short lived access token live longer
     */
    public function extendFacebookAccessTokenLife($facebookAccessToken)
    {
        // check if its already longlived
        if (!$facebookAccessToken->isLongLived()) {
            try {
                $facebookAccessToken = $this->facebookSDK
                    ->getOAuth2Client()
                    ->getLongLivedAccessToken($facebookAccessToken->getValue())
                ;
            } catch (FacebookSDKException $e) {
                throw new FacebookHelperException('Error getting long-lived access token: ' . $e->getMessage());
            }
        }

        return $facebookAccessToken;
    }


    public function refreshLongLivedUserToken(string $userToken): string
    {
        $newAccessToken = $this->facebookSDK
            ->getOAuth2Client()
            ->getLongLivedAccessToken($userToken);
        return $newAccessToken->getValue();
    }


    private function getFacebookUser($accessToken, string $id = 'me')
    {
        try {
            $res = $this->facebookSDK->get(sprintf('/%s', $id), $accessToken->getValue());
        } catch (FacebookResponseException $e) {
            throw new FacebookHelperException('Response Error getting user' . $e->getMessage());
        } catch (FacebookSDKException $e) {
            throw new FacebookHelperException('SDK Error getting user' . $e->getMessage());
        }
        return $res->getDecodedBody();
    }


    /**
     * Create an anonymous class with the persisten handler.
     */
    private function getPersistentDataHandler(): object
    {
        if ($this->redisHelper->redisIsUp()) {
            return new class implements PersistentDataInterface
            {
                protected $sessionPrefix = 'FBRLH_';

                public function get($key)
                {
                    return Cache::store('redis')->get($this->sessionPrefix . $key);
                }

                public function set($key, $value)
                {
                    return Cache::store('redis')->set($this->sessionPrefix . $key, $value);
                }
            };
        }

        // if redis does not work cache the state in a file
        return new class implements PersistentDataInterface
        {
            protected $sessionPrefix = 'FBRLH_';

            public function get($key)
            {
                return Cache::store('file')->get($this->sessionPrefix . $key);
            }

            public function set($key, $value)
            {
                return Cache::store('file')->set($this->sessionPrefix . $key, $value);
            }
        };
    }

}
