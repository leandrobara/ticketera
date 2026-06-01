<?php

namespace App\DTO\WhatsAppSenderExtension;


class WhatsAppSenderPhonesMapObjectDTO
{

    public $leadId;
    public $variables;
    public $phoneNumber;
    public $leadContactPhoneId;
    

    public function __construct(
        string $phoneNumber,
        int $leadId,
        int $leadContactPhoneId,
        ?array $variables = null
    ) {
        $this->leadId = $leadId;
        $this->variables = $variables;
        $this->phoneNumber = $phoneNumber;
        $this->leadContactPhoneId = $leadContactPhoneId;
    }

}
