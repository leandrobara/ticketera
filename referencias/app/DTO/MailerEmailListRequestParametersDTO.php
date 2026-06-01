<?php

namespace App\DTO;

use Illuminate\Support\Collection;

class MailerEmailListRequestParametersDTO
{
    public $page = null;
    public $limit = null;
    public $fields = [];

    public $id = null;
    public $to = null;
    public $from = null;
    public $appCustomId = null;
    public $openTrackingId = null;
    public $massiveSendingId = null;
    public $appCustomMassiveId = null;


    public static function buildFromEmails(Collection $emails): MailerEmailListRequestParametersDTO
    {
        $dto = new MailerEmailListRequestParametersDTO();
        $dto->id = $emails->pluck('external_id')->toArray();

        return $dto;
    }


    public function toArray()
    {
        $arr = [];
        if ($this->id) {
            $arr['filters']['id'] = $this->id;
        }
        if ($this->to) {
            $arr['filters']['to'] = $this->to;
        }
        if ($this->to) {
            $arr['filters']['from'] = $this->from;
        }
        if ($this->appCustomId) {
            $arr['filters']['app_custom_id'] = $this->massiveSendingId;
        }
        if ($this->openTrackingId) {
            $arr['filters']['open_tracking_id'] = $this->openTranckingId;
        }
        if ($this->massiveSendingId) {
            $arr['filters']['massive_sending_id'] = $this->appCustomId;
        }
        if ($this->appCustomMassiveId) {
            $arr['filters']['app_custom_massive_id'] = $this->appCustomMassiveId;
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
