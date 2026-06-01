<?php

namespace App\Helpers;

use DateTime;
use Exception;
use JsonException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\DTO\FacebookPage\ClientFacebookPageLeadGenDTO;
use App\Exceptions\Helpers\EventsLog\EventsLogParamException;
use App\Exceptions\Helpers\EventsLog\EventsLogResponseException;


// class EventsLogAPIHelper
// {

//     private $route;
//     private $system;
//     private $secret;
//     private $timeout;
//     protected $clientyJwtAlgo;
//     protected $clientyJwtSecret;



//     public function __construct(
//         string $route,
//         string $system,
//         string $secret,
//         int $timeout,
//         string $clientyJwtSecret,
//         string $clientyJwtAlgo
//     ) {
//         $this->route = $route;
//         $this->system = $system;
//         $this->secret = $secret;
//         $this->timeout = $timeout;
//         $this->clientyJwtAlgo = $clientyJwtAlgo;
//         $this->clientyJwtSecret = $clientyJwtSecret;
//     }


//     public function findLogs(array $filters): array
//     {
//         try {
//             $token = $this->getJWT();
//             $filters = $this->checkFindFilters($filters);
//             $filters['system'] = $this->system;
//             $route = $this->route . '/list';
//             $response = Http::withOptions(['verify' => false])->withToken($token)->post($route, $filters);
//         } catch (Exception $e) {
//             throw new EventsLogResponseException($e->getMessage());
//         }
//         $logs = $this->getJsonResponse($response->body());
//         return $logs;
//     }


//     public function createLog(array $data)
//     {
//         try {
//             $token = $this->getJWT();
//             $this->checkCreateData($data);
//             $data = $this->addHash($data);
//             $data['system'] = $this->system;
//             $response = Http::withToken($token)
//                 ->asJson()
//                 ->timeout($this->timeout)
//                 ->withOptions(['verify' => false])
//                 ->post($this->route, $data)->body()
//             ;
//         } catch (Exception $e) {
//             throw new EventsLogResponseException($e->getMessage());
//         }
//         return $this->getJsonResponse($response);
//     }


//     // public function createFacebookLog(array $data)
//     // {
//     //     try {
//     //         $token = $this->getJWT();
//     //         $route = $this->route . '/facebook';
//     //         $this->checkCreateData($data);
//     //         $data = $this->addHash($data);
//     //         $data['system'] = $this->system;
//     //         $response = Http::withToken($token)
//     //             ->asJson()
//     //             ->timeout($this->timeout)
//     //             ->withOptions(['verify' => false])
//     //             ->post($route, $data)->body()
//     //         ;
//     //     } catch (Exception $e) {
//     //         throw new EventsLogResponseException($e->getMessage());
//     //     }
//     //     return $this->getJsonResponse($response);
//     // }


//     // public function createEmailValidationResponseLog(array $data)
//     // {
//     //     try {
//     //         $token = $this->getJWT();
//     //         $route = $this->route . '/email_validation_response';
//     //         $this->checkCreateData($data);
//     //         $data = $this->addHash($data);
//     //         $data['system'] = $this->system;
//     //         $response = Http::withToken($token)
//     //             ->asJson()
//     //             ->timeout($this->timeout)
//     //             ->withOptions(['verify' => false])
//     //             ->post($route, $data)
//     //             ->body()
//     //         ;
//     //     } catch (Exception $e) {
//     //         throw new EventsLogResponseException($e->getMessage());
//     //     }
//     //     return $this->getJsonResponse($response);
//     // }
    

//     public function setMultipleLogClientId(array $docsInfo)
//     {
//         $token = $this->getJWT();
//         $route = $this->route . '/log/client_id/multiple';
//         $data = ['docs' => $docsInfo];
//         $response = Http::withToken($token)
//             ->asJson()
//             ->timeout($this->timeout)
//             ->withOptions(['verify' => false])
//             ->put($route, $data)->body()
//         ;
//         return $this->getJsonResponse($response);
//     }


//     public function setMultipleLogUserAndClientId(array $docsInfo)
//     {
//         $token = $this->getJWT();
//         $route = $this->route . '/log/user_and_client_id/multiple';
//         $data = ['docs' => $docsInfo];
//         $response = Http::withToken($token)
//             ->asJson()
//             ->timeout($this->timeout)
//             ->withOptions(['verify' => false])
//             ->put($route, $data)->body()
//         ;
//         return $this->getJsonResponse($response);
//     }


//     private function getJsonResponse($response): array
//     {
//         try {
//             $json = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
//             if (!$json['success']) {
//                 throw new EventsLogResponseException($json['error']['message'], $json['error']['code']);
//             }
//         } catch (JsonException $e) {
//             throw new EventsLogResponseException('Malformed Json Response');
//         }
//         return $json;
//     }


//     private function checkFindFilters(array $data): array
//     {
//         $filters = ['event', 'limit', 'offset', 'system', 'date_end', 'date_start', 'log_filters', 'fields', 'ids'];
//         $validated = ['sort' => ['createdAtTs' => 'desc']];
//         foreach ($filters as $filter) {
//             if (isset($data[$filter])) {
//                 $validated[$filter] = $data[$filter];
//             }
//         }
//         return $validated;
//     }

//     private function checkCreateData(array $data)
//     {
//         $params = ['event', 'log'];
//         foreach ($params as $param) {
//             if (!isset($data[$param])) {
//                 throw new EventsLogParamException('The parameter ' . $param . ' is mandatory', 400);
//             }
//         }
//     }


//     private function addHash(array $data): array
//     {
//         $log = serialize($this->convertFieldsToString($data['log']));
//         $data['hash'] = md5($this->secret . $this->system . $data['event'] . $log);
//         return $data;
//     }


//     private function convertFieldsToString(array $array): array
//     {
//         $converted = [];
//         foreach ($array as $i => $row) {
//             if (is_array($row)) {
//                 $converted[$i] = $this->convertFieldsToString($row);
//             } else {
//                 $converted[$i] = trim(strval($row));
//             }
//         }
//         return $converted;
//     }


//     // FOR TEST ONLY
//     public function createMockLog(array $data)
//     {
//         try {
//             $token = $this->getJWT();
//             $route = $this->route . '/mock';
//             $data = $this->addHash($data);
//             $data['system'] = $this->system;
//             $response = Http::withToken($token)->asJson()->timeout($this->timeout)->post($route, $data)->body();
//         } catch (Exception $e) {
//             throw new EventsLogResponseException($e->getMessage());
//         }
//         return $this->getJsonResponse($response);
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
