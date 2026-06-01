<?php

namespace App\DTO\WAPI;

use DateTime;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;


class WAPIChatDTO
{

    public string $id;
    public string $name;
    public bool $isGroup;
    public ?int $timestamp;
    public array $contacts;
    public ?array $lastMessage;
    

    public function __construct(array $WAPIChat)
    {
        $this->id = $WAPIChat['id'];
        $this->name = $WAPIChat['name'];
        $this->contacts = $WAPIChat['contacts'];
        $this->lastMessage = $WAPIChat['lastMessage'];
        $this->isGroup = $WAPIChat['isGroup'] ?? false;
        $this->timestamp = $WAPIChat['timestamp'] ?? null;
    }


    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'isGroup' => $this->isGroup,
            'contacts' => $this->contacts,
            'timestamp' => $this->timestamp,
            'lastMessage' => $this->lastMessage,
        ];
    }

}

