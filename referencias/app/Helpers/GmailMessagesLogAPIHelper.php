<?php

namespace App\Helpers;

use DateTime;
use Exception;
use JsonException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Exceptions\Helpers\EventsLog\GmailMessagesLogParamException;
use App\Exceptions\Helpers\EventsLog\GmailMessagesLogResponseException;


// class GmailMessagesLogAPIHelper
// {

//     private $route;
//     private $secret;
//     private $timeout;
//     protected $clientyJwtAlgo;
//     protected $clientyJwtSecret;


//     public function __construct(
//         string $route,
//         string $secret,
//         int $timeout,
//         string $clientyJwtSecret,
//         string $clientyJwtAlgo
//     ) {
//         $this->route = $route;
//         $this->secret = $secret;
//         $this->timeout = $timeout;
//         $this->clientyJwtAlgo = $clientyJwtAlgo;
//         $this->clientyJwtSecret = $clientyJwtSecret;
//     }


//     public function list(array $params): array
//     {
//         try {
//             // dd($params);
//             $token = $this->getJWT();
//             $response = Http::withToken($token)
//                 ->withOptions(['verify' => false])
//                 ->get($this->route . '/gmail-message', $params)
//             ;
//             // die($response->body());
//         } catch (Exception $e) {
//             throw new GmailMessagesLogResponseException($e->getMessage());
//         }
//         $response = $this->getJsonResponse($response->body());
//         return $response;
//     }


//     public function count(array $params): int
//     {
//         try {
//             $token = $this->getJWT();
//             $response = Http::withToken($token)
//                 ->withOptions(['verify' => false])
//                 ->get($this->route . '/gmail-message/count', $params)
//             ;
//             // dump($this->route . '/gmail-message/count', $response->body());
//         } catch (Exception $e) {
//             throw new GmailMessagesLogResponseException($e->getMessage());
//         }
//         $response = $this->getJsonResponse($response->body());
//         return $response['data'];
//     }


//     public function storeNewMessage(array $storeData): array
//     {
//         try {
//             $this->validateStoreData($storeData);
//             $storeData = $this->convertFieldsToString($storeData);
//             $storeData = $this->addHashToData($storeData);
//             $endpoint = $this->route . '/gmail-message';
//             // die(Http::post($endpoint, $storeData)->body());
//             $token = $this->getJWT();
//             $response = Http::withToken($token)
//                 ->asJson()
//                 ->timeout($this->timeout)
//                 ->withOptions(['verify' => false])
//                 ->post($endpoint, $storeData)
//                 ->body()
//             ;
//         } catch (Exception $e) {
//             throw new GmailMessagesLogResponseException($e->getMessage());
//         }
//         return $this->getJsonResponse($response);
//     }


//     private function getJsonResponse($response): array
//     {
//         try {
//             $json = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
//             if (!$json['success']) {
//                 throw new GmailMessagesLogResponseException(
//                     $json['error']['message'], $json['error']['code'], $json['debug'] ?? null,
//                 );
//             }
//         } catch (JsonException $e) {
//             throw new GmailMessagesLogResponseException('Malformed Json Response');
//         }
//         return $json;
//     }


//     private function validateStoreData(array $data): void
//     {
//         $params = [
//             'body',
//             'gmailId',
//             'headers',
//             'subject',
//             'snippet',
//             'threadId',
//             'sentDate',
//             // 'emailNameTo',
//             'sentMessageId',
//             // 'emailNameFrom',
//             'emailAddressTo',
//             'clientyMetadata',
//             'emailAddressFrom',
//             'previousSentMessageId',
//             'previousSentMessagesIds',
//             'isResponseToClientyUser',
//             'isResponseFromClientyUser',
//         ];
//         foreach ($params as $param) {
//             if (!isset($data[$param])) {
//                 throw new GmailMessagesLogParamException('The parameter ' . $param . ' is mandatory', 400);
//             }
//         }
//     }


//     private function addHashToData(array $data): array
//     {
//         $data['hash'] = md5($this->secret . $data['gmailId'] . $data['threadId'] . $data['body'] . $data['subject']);
//         return $data;
//     }


//     private function convertFieldsToString(array $array): array
//     {
//         $converted = [];
//         foreach ($array as $i => $row) {
//             if ($row === null || is_bool($row)) {
//                 $converted[$i] = $row;
//                 continue;
//             }
//             if (is_array($row)) {
//                 $converted[$i] = $this->convertFieldsToString($row);
//                 continue;
//             }
//             $converted[$i] = trim(strval($row));
//         }
//         return $converted;
//     }


//     private function getJWT()
//     {
//         $jwtInfo = [
//             'sub' => 'clienty_crm',
//             'exp' => (new DateTime('+' . $this->timeout . ' seconds'))->getTimestamp()
//         ];
//         return JwtHelper::encode($jwtInfo, $this->clientyJwtSecret, $this->clientyJwtAlgo);
//     }

// }
