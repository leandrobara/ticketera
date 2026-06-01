<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;


class MailsSoValidatorHelper
{

    private $apiKey = null;
    private $lastValidationInfo = [];

    const DOMAIN = 'https://api.mails.so/v1/';
    const EMAIL_VALIDATION_ENDPOINT = 'validate?email={emailAddress}';


    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }


    public function isValidEmail(string $emailAddress): ?bool
    {
        $this->lastValidationInfo = null;

        $emailAddress = urlencode(trim(strtolower($emailAddress)));
        $emailAddress = str_replace('%40', '@', $emailAddress);

        $uri = self::EMAIL_VALIDATION_ENDPOINT;
        $uri = Str::replaceFirst('{emailAddress}', $emailAddress, $uri);
        
        $endpoint = self::DOMAIN . $uri;

        try {
            $response = Http::asJson()
                ->timeout(50)
                ->withOptions(['verify' => false])
                ->withHeaders(['x-mails-api-key' => $this->apiKey])
                ->get($endpoint)
            ;
        } catch (Exception $e) {
            report($e);
            return null;
        }

        $this->lastValidationInfo = $this->getJsonResponse($response->body());

        $error = $this->lastValidationInfo['error'];
        $data = $this->lastValidationInfo['data'] ?? null;

        $score = $data['score'] ?? 999;
        $reason = $data['reason'] ?? null;
        $result = $data['result'] ?? null;

        if (!$data || $error || $score == 999 || $result == 'deliverable') {
            return true; // Es válido por default
        }
        if ($result == 'undeliverable') {
            return false;
        }
        if ($reason == 'invalid_domain') {
            return false;
        }
        if ($result == 'unknown') {
            return $score >= 40;
        }

        return $score >= 40;
    }


    public function getLastValidationInfo()
    {
        return $this->lastValidationInfo;
    }


    protected function getJsonResponse($response): array
    {
        $json = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if ($json['error'] !== null) {
            throw new Exception((string) $response);
        }
        return $json;
    }

}
