<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;


class EmailListVerifyValidatorHelper
{

    private $apiKey = null;

    const DOMAIN = 'https://apps.emaillistverify.com/api/';
    const EMAIL_VALIDATION_ENDPOINT = 'verifyEmail?secret={apiKey}&email={emailAddress}';


    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }


    public function isValidEmail(string $emailAddress): ?bool
    {
        $emailAddress = urlencode(trim(strtolower($emailAddress)));
        $emailAddress = str_replace('%40', '@', $emailAddress);

        $uri = self::EMAIL_VALIDATION_ENDPOINT;
        $uri = Str::replaceFirst('{apiKey}', $this->apiKey, $uri);
        $uri = Str::replaceFirst('{emailAddress}', $emailAddress, $uri);
        $endpoint = self::DOMAIN . $uri . '&timeout=40';

        try {
            $responseStr = Http::timeout(45)->withOptions(['verify' => false])->get($endpoint)->body();
        } catch (Exception $e) {
            report($e);
            return null;
        }

        if ($responseStr == 'unknown') {
            return null;
        }
        
        // $validResponses = ['ok', 'ok_for_all', 'antispam_system', 'smtp_protocol'];
        $invalidResponses = [
            'email_disabled', 'dead_server', 'invalid_mx', 'spamtrap', 'disposable', 'invalid_syntax'
        ];
        if (in_array($responseStr, $invalidResponses)) {
            return false;
        }
        return true;
    }

}
