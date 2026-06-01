<?php

namespace App\DTO;

use Illuminate\Support\Collection;

class MailerMassiveEmailListRequestParametersDTO
{
    public $page = null;
    public $limit = null;
    public $fields = [];

    public $to = null;
    public $from = null;
    public $massive_id = null;
    public $appCustomId = null;
    public $openTrackingId = null;
    public $massiveSendingId = null;
    public $appCustomMassiveId = null;


    public static function buildFromEmails(Collection $emails): MailerMassiveEmailListRequestParametersDTO
    {
        $dto = new MailerMassiveEmailListRequestParametersDTO();
        $dto->massive_id = $emails->pluck('external_massive_id')->toArray();

        return $dto;
    }


    public function toArray()
    {
        $arr = [];
        if ($this->massive_id) {
            $arr['filters']['massive_sending_id'] = $this->massive_id;
        }
        if ($this->fields) {
            $arr['fields'] = $this->fields;
        }
        if ($this->limit) {
            $arr['limit'] = $this->limit;
        }

        return $arr;
    }
}
