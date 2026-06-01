<?php

namespace App\Console\Commands;

use Exception;
use Google_Client;
use App\Models\User;
use App\Models\Lead;
use Google\Service\Gmail;
use Illuminate\Console\Command;
use App\Helpers\GoogleAPIHelper;
use Google\Service\PeopleService;
use App\Models\GoogleAPIUserToken;
use Illuminate\Support\Facades\DB;
use App\Models\GoogleAPIUserContact;
use Illuminate\Support\Facades\Artisan;
use App\Services\API\GoogleCommonAPIService;
use App\Services\API\GoogleAPIUserTokenService;


class GoogleAPIPeopleAuthorizeCommand extends Command
{

    protected $description = 'Authorize client to use google api';
    protected $signature = 'google-api-people:authorize {--client-id=}';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $clientId = (int) ($this->option('client-id') ?? 0);

        // $service = resolve(GoogleCommonAPIService::class);
        
        // $user = User::find(2);
        // $contacts = $service->findAllContactsByUser($user);
        // dd($contacts);
        
        // $userContact = GoogleAPIUserContact::find(1);
        // $contact = $service->findContactByUserContactModel($userContact);
        // dd($contact);
        
        // $userContact = GoogleAPIUserContact::find(1);
        // $contact = $service->updateContactFromUserContactModel($userContact);
        // dd($contact);
        
        // $newContact = $service->createNewContactFromLead($lead);
        // dd($newContact);

        // $client = $this->_getClient();
        // $this->showContacts($client);
        // $this->showLabels($client);
        // $this->showEmails($client);
    }


    public function showLabels($client)
    {
        $service = new Gmail($client);
        $user = 'me';
        $results = $service->users_labels->listUsersLabels($user);

        if (count($results->getLabels()) == 0) {
            $this->warn("No labels found.");
        } else {
            $this->info("Labels:");
            foreach ($results->getLabels() as $label) {
                $this->info("- {$label->getName()}");
            }
        }
    }

    public function showEmails($client)
    {
        $user = 'me';
        $service = new Gmail($client);
        $results = $service->users_messages->listUsersMessages($user, ['maxResults' => 2]);
        $messages = $results->getMessages();
        foreach ($messages as $message) {
            $populatedMessage = $service->users_messages->get($user, $message->id);
            
            $parts = $populatedMessage->getPayload()->getParts();
            if (is_object($parts)) {
                $parts = $parts[0]->getParts();
            }
            if ($parts) {
                $bodyData = $this->searchBodyInPartsHeaders($parts);
            } else {
                $bodyData = $populatedMessage->getPayload()->getBody()->getData();
                $bodyData = ($bodyData) ? base64_decode(strtr($bodyData, '-_', '+/')) : null;
            }

            $this->info('= Body:');
            $this->info($bodyData);
            $this->info('------------------------');
        }
    }


    public function showContacts($client)
    {
        $service = new PeopleService($client);
        $optParams = ['pageSize' => 1000, 'personFields' => 'names,emailAddresses,organizations,phoneNumbers'];
        $results = $service->people_connections->listPeopleConnections('people/me', $optParams);

        // Si no es NULL, pasarlo a $opts['pageToken']
        $nextPageToken = $results->nextPageToken;

        foreach ($results->getConnections() as $person) {
            $names = $person->getNames();
            $emails = $person->getEmailAddresses();
            $phones = $person->getPhoneNumbers();

            $name = $names[0] ?? null;
            $email = $emails[0] ?? null;
            $phone = $phones[0] ?? null;
            if ($name) {
                $this->info('Name: ' . $name->getDisplayName());
            }
            if ($phone) {
                $this->info('Phone: ' . $phone->getValue());
            }
            if ($email) {
                $this->info('Email: ' . $email->getValue());
            }
            $this->info('------------------------');
        }
    }


    public function _getClient()
    {
        $credentialsDir = __DIR__ . '/../../../resources/credentials';
        $client = new Google_Client();
        $client->setApplicationName('Clienty');
        $client->setScopes([
            Gmail::GMAIL_SEND,
            Gmail::GMAIL_READONLY,
            PeopleService::CONTACTS,
        ]);
        $client->setAuthConfig($credentialsDir . '/clienty_credentials.json');
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $state = (serialize(['subdomain' => 'clienty']));
        $client->setState($state);

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.

        $tokenPath = $credentialsDir . '/notifications_token.json';
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();
                $this->info("Open the following link in your browser:\n$authUrl\n");
                $this->info("Enter verification code: ");
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(join(', ', $accessToken));
                }
            }
            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
        return $client;
    }



    private function searchBodyInPartsHeaders($parts)
    {
        $resultParts = [];

        foreach ($parts as $part) {
            $result = '';

            $body = $part->getBody();
            $headers = $part->getHeaders();
            $moreParts = $part->getParts();
            // var_dump($body, $headers, $moreParts);exit;
            
            #(si hay parts dentro del part).
            foreach ($moreParts as $anotherPart) {
                if (!$anotherPart->getBody() || !$anotherPart->getBody()->getData()) {
                    continue;
                }
                if (!$anotherPart->getMimeType()) {
                    continue;
                }
                if ($anotherPart->getMimeType() != 'text/html') {
                    continue;
                }
                $result .= base64_decode( strtr($anotherPart->getBody()->getData(), '-_', '+/') );
            }
            
            foreach ($headers as $header) {
                $headerName = $header->getName();
                $headerValue = $header->getValue();
                if ($headerName != 'Content-Type') {
                    continue;
                }
                if (stripos($headerValue, 'text/plain') === false && stripos($headerValue, 'text/html') === false) {
                    continue;
                }
                                
                $resultParts[$headerValue] = $result . base64_decode( strtr($body->getData(), '-_', '+/') );
            }

            if (!$resultParts) {
                $resultParts['text/html'] = $result;
            }
        }
        
        $resultBody = implode('', $resultParts);
        return $resultBody;
    }


    protected function base64UrlEncode($inputStr)
    {
        return strtr(base64_encode($inputStr), '+/=', '-_,');
    }

    protected function base64UrlDecode($inputStr)
    {
        return base64_decode(strtr($inputStr, '-_,', '+/='));
    }

}
