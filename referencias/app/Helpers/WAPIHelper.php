<?php

namespace App\Helpers;

use DateTime;
use Exception;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use App\DTO\WAPI\WAPISyncStatusDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use App\DTO\WAPI\WAPIHelperMessageDTO;
use App\Exceptions\Helpers\WAPIHelper\WAPIHelperException;
use App\Exceptions\Helpers\WAPIHelper\WAPIHelperUserNotSyncedException;


class WAPIHelper
{

    public function __construct(
        protected int $timeout,
        protected string $wapiRoute,
        protected string $clientyJwtAlgo,
        protected string $clientyJwtSecret,
        protected string $wapEngine = 'wwebjs',
    ) {
    }


    public function setRouteAndEngineFromUser(User $user): WAPIHelper
    {
        $wapiRoute = $user->wapi_route;
        if (config('app.wapi.redirect_to_route')) {
            $wapiRoute = config('app.wapi.redirect_to_route');
        }
        $this->setWAPIRoute($wapiRoute);
        $this->setWAPEngine($user->wapi_engine);
        return $this;
    }

    public function setWAPIRoute(string $wapiRoute): WAPIHelper
    {
        $this->wapiRoute = $wapiRoute;
        return $this;
    }

    public function setWAPEngine(string $wapEngine): WAPIHelper
    {
        $this->wapEngine = $wapEngine;
        return $this;
    }


    public function sync(string $wapiSessionPhoneNumber): WAPISyncStatusDTO
    {
        $token = $this->getJWT($wapiSessionPhoneNumber);
        $endpoint = $this->wapiRoute . '/api/auth?wapEngine=' . $this->wapEngine;
        $response = Http::withToken($token)
            ->withOptions(['verify' => false])
            ->timeout($this->timeout)
            ->post($endpoint)
        ;
        $responseArr = $this->parseResponse($response);
        $dto = WAPISyncStatusDTO::buildFromWapiResponse($responseArr);
        return $dto;
    }


    public function verifyUserIsSynced(string $wapiSessionPhoneNumber): WAPISyncStatusDTO
    {
        $token = $this->getJWT($wapiSessionPhoneNumber);
        $endpoint = $this->wapiRoute . '/api/auth/me?wapEngine=' . $this->wapEngine;
        $response = Http::withToken($token)
            ->withOptions(['verify' => false])
            ->timeout($this->timeout)
            ->get($endpoint)
        ;
        $responseArr = $this->parseResponse($response);
        $dto = WAPISyncStatusDTO::buildFromWapiResponse($responseArr);
        return $dto;
    }


    public function sendMessage(WAPIHelperMessageDTO $dto): array
    {
        $endpoint = $this->wapiRoute . '/api/message/send?wapEngine=' . $this->wapEngine;
        $token = $this->getJWT($dto->wapiSessionPhoneNumber);
        $response = Http::withToken($token)
            ->timeout($this->timeout)
            ->withOptions(['verify' => false])
            ->post($endpoint, $dto->toArray())
        ;
        return $this->parseResponse($response);
    }


    public function deleteSessionFiles(string $phoneNumber): bool
    {
        $endpoint = $this->wapiRoute . '/api/auth/session-files?wapEngine=' . $this->wapEngine;
        $token = $this->getJWT($phoneNumber);
        $response = Http::withToken($token)
            ->timeout($this->timeout)
            ->withOptions(['verify' => false])
            ->delete($endpoint)
        ;
        $responseArr = $this->parseResponse($response);
        return $responseArr['data']['success'] ?? false;
    }


    public function listChats(
        string $wapiSessionPhoneNumber,
        array $opts = []
    ): array {
        $endpoint = $this->wapiRoute . '/api/chat?wapEngine=' . $this->wapEngine;
        $token = $this->getJWT($wapiSessionPhoneNumber);
        $params = [
            'limit' => $opts['limit'] ?? 100,
        ];
        $response = Http::withToken($token)
            ->timeout($this->timeout)
            ->withOptions(['verify' => false])
            ->get($endpoint, $params)
        ;
        $response = $this->parseResponse($response);
        return $response['data']['chats'] ?? [];
    }


    public function listChatMessages(
        string $wapiSessionPhoneNumber,
        string $chatPhoneNumber,
        array $opts = []
    ): array {
        $endpoint = $this->wapiRoute . '/api/message?wapEngine=' . $this->wapEngine;
        $token = $this->getJWT($wapiSessionPhoneNumber);
        $params = [
            'limit' => $opts['limit'] ?? 100,
            'phoneNumber' => $chatPhoneNumber,
        ];
        $response = Http::withToken($token)
            ->timeout($this->timeout)
            ->withOptions(['verify' => false])
            ->get($endpoint, $params)
        ;
        $response = $this->parseResponse($response);
        return $response['data']['messages'] ?? [];
    }


    public function getChatMessageMediaInfo(string $wapiSessionPhoneNumber, string $wapiChatMessageId): array
    {
        $endpoint = $this->wapiRoute . '/api/message/' . $wapiChatMessageId . '/media?wapEngine=' . $this->wapEngine;
        $token = $this->getJWT($wapiSessionPhoneNumber);
        $response = Http::withToken($token)
            ->timeout($this->timeout)
            ->withOptions(['verify' => false])
            ->get($endpoint, [])
        ;
        $response = $this->parseResponse($response);
        return $response['data'] ?? [];
    }


    private function parseResponse(Response $response): array
    {
        try {
            $responseArr = json_decode($response, true);
            if (!$responseArr || !$responseArr['success']) {
                throw new Exception('[Error on WAPI Endpoint call]' . $response->body());
            }
        } catch (Exception $e) {
            if (Str::contains($e->getMessage(), 'whatsapp_client_session_not_authenticated')) {
                throw new WAPIHelperUserNotSyncedException($e->getMessage());
            }
            throw new WAPIHelperException($e->getMessage());
        }
        return $responseArr;
    }


    private function getJWT(string $wapiSessionPhoneNumber)
    {
        $jwtInfo = [
            'sub' => 'clienty_crm',
            'sessionPhoneNumber' => $wapiSessionPhoneNumber,
            'exp' => (new DateTime('+' . $this->timeout . ' seconds'))->getTimestamp(),
        ];
        return JwtHelper::encode($jwtInfo, $this->clientyJwtSecret, $this->clientyJwtAlgo);
    }

}
