<?php

namespace App\Helpers;

use DateTime;
use Exception;
use JsonException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;


class WAPIMessagesLogAPIHelper
{

    private $route;
    private $secret;
    private $timeout;
    protected $clientyJwtAlgo;
    protected $clientyJwtSecret;


    public function __construct(
        string $route,
        string $secret,
        int $timeout,
        string $clientyJwtSecret,
        string $clientyJwtAlgo
    ) {
        $this->route = $route;
        $this->secret = $secret;
        $this->timeout = $timeout;
        $this->clientyJwtAlgo = $clientyJwtAlgo;
        $this->clientyJwtSecret = $clientyJwtSecret;
    }


    // public function createLog(array $sentMessageData): array
    // {
    //     $this->validateStoreData($sentMessageData);
    //     $sentMessageData = $this->convertFieldsToString($sentMessageData);
    //     $endpoint = $this->route . '/wapi-message';
    //     // die(Http::post($endpoint, $sentMessageData)->body());
    //     $token = $this->getJWT();
    //     $response = Http::withToken($token)
    //         ->asJson()
    //         ->timeout($this->timeout)
    //         ->withOptions(['verify' => false])
    //         ->post($endpoint, $sentMessageData)
    //         ->body()
    //     ;
    //     return $this->getJsonResponse($response);
    // }


    private function getJsonResponse($response): array
    {
        try {
            $json = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (!$json['success']) {
                throw new GmailMessagesLogResponseException(
                    $json['error']['message'], $json['error']['code'], $json['debug'] ?? null,
                );
            }
        } catch (JsonException $e) {
            throw new GmailMessagesLogResponseException('Malformed Json Response');
        }
        return $json;
    }


    private function validateStoreData(array $data): void
    {
        $params = ['log', 'datetime'];
        foreach ($params as $param) {
            if (!isset($data[$param])) {
                throw new Exception('The parameter ' . $param . ' is mandatory', 400);
            }
        }
    }


    private function convertFieldsToString(array $array): array
    {
        $converted = [];
        foreach ($array as $i => $row) {
            if ($row === null || is_bool($row)) {
                $converted[$i] = $row;
                continue;
            }
            if (is_array($row)) {
                $converted[$i] = $this->convertFieldsToString($row);
                continue;
            }
            $converted[$i] = trim(strval($row));
        }
        return $converted;
    }


    private function getJWT()
    {
        $jwtInfo = [
            'sub' => 'clienty_crm',
            'exp' => (new DateTime('+' . $this->timeout . ' seconds'))->getTimestamp()
        ];
        return JwtHelper::encode($jwtInfo, $this->clientyJwtSecret, $this->clientyJwtAlgo);
    }

}
