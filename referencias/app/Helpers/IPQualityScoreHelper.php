<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;


class IPQualityScoreHelper
{

    private $apiKey = null;
    private $lastValidationInfo = [];

    const DOMAIN = 'https://ipqualityscore.com/api/json/';
    const EMAIL_VALIDATION_ENDPOINT = 'email/{apiKey}/{emailAddress}';


    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }


    public function isValidEmail(string $emailAddress): bool
    {
        $this->lastValidationInfo = null;

        $emailAddress = urlencode(trim(strtolower($emailAddress)));
        $emailAddress = str_replace('%40', '@', $emailAddress);

        $uri = self::EMAIL_VALIDATION_ENDPOINT;
        $uri = Str::replaceFirst('{apiKey}', $this->apiKey, $uri);
        $uri = Str::replaceFirst('{emailAddress}', $emailAddress, $uri);
        
        $endpoint = self::DOMAIN . $uri;
        $response = Http::asJson()->withOptions(['verify' => false])->timeout(60)->get($endpoint);
        $this->lastValidationInfo = $this->getJsonResponse($response->body());
        return $response['valid'];
    }


    public function getLastValidationInfo()
    {
        return $this->lastValidationInfo;
    }


    protected function getJsonResponse($response): array
    {
        $json = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (!($json['success'] ?? false)) {
            throw new Exception((string) $response);
        }
        return $json;
    }

}