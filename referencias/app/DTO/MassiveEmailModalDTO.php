<?php

namespace App\DTO;

use App\DTO\EmailQuotaInfoDTO;
use Illuminate\Support\Collection;


class MassiveEmailModalDTO
{

    public $leadContactEmails = [];
    public $emailSendingBlocked = false;
    public EmailQuotaInfoDTO $emailQuotaInfoDTO;

    public function __construct()
    {
        $this->emailQuotaInfoDTO = new EmailQuotaInfoDTO();
    }

}
