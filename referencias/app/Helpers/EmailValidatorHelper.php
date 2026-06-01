<?php

namespace App\Helpers;

use Illuminate\Support\Str;


class EmailValidatorHelper
{

    public function emailExists(string $emailAddress, bool $enableDebug = false): array
    {
        $fromDomain = 'gmail.com';
        $fromEmailAddress = 'email@gmail.com';

        if (!$emailAddress || !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            return [
                'is_valid' => false,
                'is_gmail' => null,
                'is_decisive' => true,
                'connection_log' => null,
                'connection_tested' => false,
                'reason' => 'invalid_address',
            ];
        }

        $emailParts = explode('@', $emailAddress);
        $domain = $emailParts[1];
        $originalDomain = $domain;
        
        getmxrr($domain, $mxHosts);

        // Si no hay MX records, intentar con el dominio padre (para subdominios)
        if (empty($mxHosts)) {
            $domainParts = explode('.', $domain);
            
            // Mientras tenga más de 2 partes (ej: energy.remax.com.ve -> remax.com.ve -> com.ve)
            while (count($domainParts) > 2 && empty($mxHosts)) {
                array_shift($domainParts);
                $parentDomain = implode('.', $domainParts);
                getmxrr($parentDomain, $mxHosts);
                
                if ($enableDebug) {
                    var_dump("No MX for $domain, trying parent: $parentDomain", $mxHosts);
                }
                
                $domain = $parentDomain;
            }
        }
        
        if (empty($mxHosts)) {
            if ($enableDebug) {
                var_dump("No MX Records found for $originalDomain or any parent domain");
            }
            return [
                'is_valid' => false,
                'is_gmail' => null,
                'is_decisive' => true,
                'connection_log' => null,
                'reason' => 'no_mx_records',
                'connection_tested' => false,
            ];
        }

        if ($enableDebug) {
            var_dump("Using MX from domain: $domain", $mxHosts);
        }

        $isValid = true;
        // Por el momento chequea UN SOLO HOST MX, EN LUGAR DE TODOS
        foreach ($mxHosts as $mxHost) {
            $connectionLog = [];

            $connection = @fsockopen($mxHost, 25, $errno, $errstr, 30);
            if (!$connection) {
                $connectionLog['Connection'] = "ERROR";
                $connectionLog['ConnectionResponse'] = $errstr;
                if ($enableDebug) {
                    var_dump($connectionLog);
                }
                return [
                    'is_valid' => false,
                    'is_gmail' => null,
                    'is_decisive' => false,
                    'connection_log' => null,
                    'connection_tested' => true,
                    'reason' => 'connection_error',
                ];
            }

            // Chequeo hasta acá, solo que exista el dominio. Salvo Gmail que sigue.
            // Uso $originalDomain para el chequeo de gmail (el email original)
            if (strtolower($originalDomain) != 'gmail.com' && !Str::endsWith(strtolower($mxHost), 'google.com')) {
                @fclose($connection);
                return [
                    'is_valid' => true,
                    'is_gmail' => false,
                    'is_decisive' => false,
                    'connection_log' => null,
                    'connection_tested' => false,
                    'reason' => 'validation_not_performed',
                ];
            }
            

            $connectionLog['Connection'] = "SUCCESS";
            $connectionLog['ConnectionResponse'] = $errstr;
            
            @fputs($connection, "HELO $fromDomain\r\n");
            $response = fgets($connection);

            $connectionLog['HELO'] = $response;
            $heloCode = substr($connectionLog['HELO'], 0, 1);

            // send the email from..
            @fputs($connection, "MAIL FROM: <$fromEmailAddress>\r\n");
            $mailFromResponse = fgets($connection);
            $connectionLog['MailFromResponse'] = $mailFromResponse;

            // send the email to..
            @fputs($connection, "RCPT TO: <$emailAddress>\r\n");
            $mailToResponse = fgets($connection);
            $connectionLog['MailToResponse'] = $mailToResponse;

            // get the response code..
            $responseCode = substr($connectionLog['MailToResponse'], 0, 3);
            $sBaseResponseFromCode = substr($connectionLog['MailFromResponse'], 0, 1);
            $responseCode = substr($responseCode, 0, 1);

            // say goodbye..
            @fputs($connection, "QUIT\r\n");
            $quitResponse = fgets($connection);
            
            // get the quit code and response..
            $quitCode = substr($quitResponse, 0, 3);
            $quitCode = substr($quitCode, 0, 1);
            $connectionLog['QuitResponse'] = $quitResponse;
            $connectionLog['QuitCode'] = $quitCode;

            @fclose($connection);
            
            if ($enableDebug) {
                var_dump($connectionLog);
            }

            // Si es hotmail, hago skip
            $opts = ['hotmail', 'live', 'outlook', 'spam'];
            if (Str::contains($connectionLog['HELO'], $opts)) {
                return [
                    'is_valid' => true,
                    'is_gmail' => false,
                    'is_decisive' => false,
                    'connection_tested' => true,
                    'reason' => 'is_hotmail_or_spam',
                    'connection_log' => $connectionLog,
                ];
            }

            $result = [
                'is_gmail' => true,
                'is_decisive' => true,
                'connection_tested' => true,
                'connection_log' => $connectionLog,
            ];

            if ($connectionLog['HELO'] !== false && $heloCode != '2') {
                return $result + ['is_valid' => false, 'reason' => 'bad_helo_code'];
            }
            if (stripos($mailToResponse, 'unknown') !== false) {
                return $result + ['is_valid' => false, 'reason' => 'unknown_response'];
            }
            if (stripos($mailToResponse, 'No such recipient') !== false) {
                return $result + ['is_valid' => false, 'reason' => 'no_such_recipient'];
            }
            if ($responseCode == '5' || $quitCode == '5') {
                return $result + ['is_valid' => false, 'reason' => 'bad_response_code'];
            }
            return $result + ['is_valid' => true, 'reason' => 'deliverable'];
        }

        // Acá no debería llegar nunca.
        return ['is_valid' => true];
    }

}
