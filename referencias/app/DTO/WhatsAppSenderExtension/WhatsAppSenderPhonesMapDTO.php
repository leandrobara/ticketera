<?php

namespace App\DTO\WhatsAppSenderExtension;

use Illuminate\Support\Collection;
use App\DTO\WhatsAppSenderExtension\WhatsAppSenderPhonesMapObjectDTO;


class WhatsAppSenderPhonesMapDTO
{

    // Collection
    public $phonesMap;

    
    public function __construct(array $phonesMapArray)
    {
        if (!$phonesMapArray) {
            throw new Exception('whatsapp_sender_phones_map_dto_empty_phones_map_array');
        }
        $this->phonesMap = new Collection();
        foreach ($phonesMapArray as $row) {
            $phonesMapObjectDTO = new WhatsAppSenderPhonesMapObjectDTO(
                $row['phoneNumber'], $row['leadId'], $row['leadContactPhoneId'],
            );
            if ($row['variables'] ?? null) {
                $phonesMapObjectDTO->variables = $row['variables'];
            }
            $this->phonesMap->push($phonesMapObjectDTO);
        }
    }


    public function isEmpty(): bool
    {
        return $this->phonesMap->isEmpty();
    }

}
