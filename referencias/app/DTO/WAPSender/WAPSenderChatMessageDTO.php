<?php

namespace App\DTO\WAPSender;

use DateTime;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;


class WAPSenderChatMessageDTO
{

    public string $id;
    public string $type;
    public ?string $body;
    public bool $hasMedia;
    public int $sentDateTs;
    public ?string $caption;
    public string $numberTo;
    public bool $isForwarded;
    public ?string $mimeType;
    public ?string $mediaData;
    public string $numberFrom;
    public DateTime $sentDate;
    public bool $isFromContact;
    public bool $isFromClientyUser;


    // Esta estructura viene en formato array (ejemplos de json completos al final de esta clase).
    public function __construct(array $WAPSenderChatMsg)
    {
        $this->id = $WAPSenderChatMsg['id'];
        $this->type = $WAPSenderChatMsg['type'];
        $this->body = $WAPSenderChatMsg['body'];
        $this->numberTo = $WAPSenderChatMsg['to'];
        $this->caption = $WAPSenderChatMsg['caption'];
        $this->numberFrom = $WAPSenderChatMsg['from'];
        $this->hasMedia = $WAPSenderChatMsg['hasMedia'];
        $this->mimeType = $WAPSenderChatMsg['mimetype'];
        $this->mediaData = $WAPSenderChatMsg['mediaData'];
        $this->isFromContact = !$WAPSenderChatMsg['fromMe'];
        $this->isForwarded = $WAPSenderChatMsg['isForwarded'];
        $this->isFromClientyUser = $WAPSenderChatMsg['fromMe'];
        
        $this->sentDateTs = $WAPSenderChatMsg['timestamp'];
        $this->sentDate = new DateTime("@{$WAPSenderChatMsg['timestamp']}");
    }


    public function toArray()
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'body' => $this->body,
            'mimeType' => $this->mimeType,
            'numberTo' => $this->numberTo,
            'hasMedia' => $this->hasMedia,
            'sentDate' => $this->sentDate,
            'mediaData' => $this->mediaData,
            'numberFrom' => $this->numberFrom,
            'sentDateTs' => $this->sentDateTs,
            'isForwarded' => $this->isForwarded,
            'isFromContact' => $this->isFromContact,
            'isFromClientyUser' => $this->isFromClientyUser,
        ];
    }

}