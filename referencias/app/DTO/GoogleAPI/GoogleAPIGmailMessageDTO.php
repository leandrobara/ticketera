<?php

namespace App\DTO\GoogleAPI;

use DateTime;
use Exception;
use DateTimeZone;
use App\Models\User;
use Google\Service\Gmail\Message;
use App\Models\GoogleAPIUserContact;
use App\Helpers\GoogleGmailAPIHelper;
use App\Models\MongoDB\GmailMessageLog;
use Google\Service\PeopleService\Person;


class GoogleAPIGmailMessageDTO
{

    const BUILD_TYPE_FULL = 'full';
    const BUILD_TYPE_LIGHT = 'light';

    public $gmailId;

    public $lead;

    public $subject;
    public $snippet;
    public $sentDate;
    public $threadId;
    public $emailNameTo;
    public $headers = [];
    public $sentMessageId; // ID de envío
    public $emailNameFrom;
    public $emailAddressTo;
    public $clientyMetadata;
    public $emailAddressFrom;
    public $previousSentMessageId;
    public $isResponseToClientyUser;
    public $isResponseFromClientyUser;
    public $previousSentMessagesIds = [];
    
    public $buildType;
    public $isFullBuildType;


    public static function buildFromGoogleAPIGmailMessage(
        Message $gmailMessage,
        array $opts = []
    ): GoogleAPIGmailMessageDTO {
        $dto = new GoogleAPIGmailMessageDTO();
        $helper = resolve(GoogleGmailAPIHelper::class);

        $linkedEmailAddr = $opts['linkedEmailAddr'] ?? null;
        $dto->buildType = $opts['buildType'] ?? self::BUILD_TYPE_FULL;
        $dto->isFullBuildType = $dto->buildType == self::BUILD_TYPE_FULL;

        if ($dto->isFullBuildType && !$gmailMessage->getPayload()) {
            throw new Exception('GoogleAPIGmailMessageDTO: Gmail Message is not populated');
        }

        $dto->gmailId = $gmailMessage->id;
        $dto->snippet = $gmailMessage->snippet;
        $dto->threadId = $gmailMessage->threadId;

        $sentTs = intval($gmailMessage->internalDate) / 1000;
        $dto->sentDate = (new DateTime('@' . $sentTs))->setTimezone(new DateTimeZone('UTC'));
        
        if ($dto->isFullBuildType) {
            $dto->headers = collect($gmailMessage->getPayload()->getHeaders())->map(function ($h) {
                return ['name' => $h['name'], 'value' => $h['value']];
            })->toArray();
            $dto->subject = $helper->getSubject($gmailMessage);
            if (!trim($dto->subject)) {
                $dto->subject = '<SIN ASUNTO>';
            }
            $dto->emailNameTo = $helper->getEmailNameTo($gmailMessage);
            $dto->emailNameFrom = $helper->getEmailNameFrom($gmailMessage);
            $dto->sentMessageId = $helper->getSentMessageId($gmailMessage);
            $dto->emailAddressTo = $helper->getEmailAddressTo($gmailMessage);
            $dto->body = $helper->getBodyStringFromGmailMessage($gmailMessage);
            $dto->emailAddressFrom = $helper->getEmailAddressFrom($gmailMessage);
            $dto->previousSentMessageId = $helper->getInReplyToId($gmailMessage);
            $dto->clientyMetadata = $helper->getClientyMetadataFromBody($dto->body);
            $dto->previousSentMessagesIds = $helper->getReferencesIds($gmailMessage);
            
            if ($linkedEmailAddr) {
                $dto->isResponseToClientyUser = $helper->isResponseToUser($gmailMessage, $linkedEmailAddr);
                $dto->isResponseFromClientyUser = $helper->isResponseFromUser($gmailMessage, $linkedEmailAddr);
            }
        }
        return $dto;
    }


    public static function buildFromMongoDoc(GmailMessageLog $gmailMessageLog): GoogleAPIGmailMessageDTO
    {
        $dto = new GoogleAPIGmailMessageDTO();

        $dto->isFullBuildType = true;
        $dto->subject = $gmailMessageLog->subject;
        $dto->gmailId = $gmailMessageLog->gmailId;
        $dto->snippet = $gmailMessageLog->snippet;
        $dto->threadId = $gmailMessageLog->threadId;
        $dto->body = $gmailMessageLog?->body ?? null;
        // $dto->buildType = self::BUILD_TYPE_FULL;
        $dto->headers = $gmailMessageLog?->headers ?? [];
        $dto->emailNameTo = $gmailMessageLog->emailNameTo;
        $dto->emailNameFrom = $gmailMessageLog->emailNameFrom;
        $dto->sentMessageId = $gmailMessageLog->sentMessageId;
        $dto->emailAddressTo = $gmailMessageLog->emailAddressTo;
        $dto->clientyMetadata = $gmailMessageLog->clientyMetadata;
        $dto->emailAddressFrom = $gmailMessageLog->emailAddressFrom;
        $dto->previousSentMessageId = $gmailMessageLog->previousSentMessageId;
        $dto->previousSentMessagesIds = $gmailMessageLog->previousSentMessagesIds;
        $dto->isResponseToClientyUser = $gmailMessageLog->isResponseToClientyUser;
        $dto->isResponseFromClientyUser = $gmailMessageLog->isResponseFromClientyUser;

        $sentDateTs = $gmailMessageLog->sentDateTs;
        $dto->sentDate = (new DateTime('@' . $sentDateTs))->setTimezone(new DateTimeZone('UTC'));
        return $dto;
    }


    public function toArray(): array
    {
        $messageArr = [
            'body' => $this->body,
            'gmailId' => $this->gmailId,
            'subject' => $this->subject,
            'snippet' => $this->snippet,
            'headers' => $this->headers,
            'threadId' => $this->threadId,
            'emailNameTo' => $this->emailNameTo,
            'sentMessageId' => $this->sentMessageId,
            'emailNameFrom' => $this->emailNameFrom,
            'emailAddressTo' => $this->emailAddressTo,
            'clientyMetadata' => $this->clientyMetadata,
            'emailAddressFrom' => $this->emailAddressFrom,
            'sentDateTs' => $this->sentDate->getTimestamp(),
            'sentDate' => $this->sentDate->format('Y-m-d\TH:i:sP'),
            'previousSentMessageId' => $this->previousSentMessageId,
            'isResponseToClientyUser' => $this->isResponseToClientyUser,
            'previousSentMessagesIds' => $this->previousSentMessagesIds,
            'isResponseFromClientyUser' => $this->isResponseFromClientyUser,
        ];
        if ($this->lead) {
            $messageArr['lead'] = $this->lead;
        }
        return $messageArr;
    }

}
