<?php

namespace App\DTO;

use Illuminate\Support\Str;


class MailerMassiveEmailDTO
{

    public $subject = null;
    public $fromName = null;
    public $emailFrom = null;
    public $sentCount = null;
    public $totalCount = null;
    public $openedCount = null;
    public $bouncedCount = null;
    public $lastSentDate = null;
    public $cancelledDate = null;
    public $firstSentDate = null;
    public $scheduledCount = null;
    public $complainedCount = null;
    public $massiveSendingId = null;
    public $unsubscribedCount = null;
    public $lastScheduledSendDate = null;
    public $firstScheduledSendDate = null;


    public static function buildFromMassiveEmail(array $massiveMailerEmail): MailerMassiveEmailDTO
    {
        $dto = new MailerMassiveEmailDTO();
        foreach ($massiveMailerEmail as $k => $v) {
            $attr = Str::of($k)->camel();
            $dto->{$attr} = $v;
        }
        return $dto;
    }


    public function toArray(): array
    {
        $arr = [];
        $arr['subject'] = $this->subject;
        $arr['from_name']  = $this->fromName;
        $arr['sent_count'] = $this->sentCount;
        $arr['email_from']  = $this->emailFrom;
        $arr['total_count'] = $this->totalCount;
        $arr['opened_count'] = $this->openedCount;
        $arr['bounced_count'] = $this->bouncedCount;
        $arr['last_sent_date'] = $this->lastSentDate;
        $arr['first_sent_date'] = $this->firstSentDate;
        $arr['scheduled_count'] = $this->scheduledCount;
        $arr['complained_count'] = $this->complainedCount;
        $arr['massive_sending_id'] = $this->massiveSendingId;
        $arr['unsubscribed_count'] = $this->unsubscribedCount;
        $arr['last_scheduled_send_date'] = $this->lastScheduledSendDate;
        $arr['first_scheduled_send_date'] = $this->firstScheduledSendDate;
        $arr['cancelled_date'] = $this->cancelledDate;

        return $arr;
    }

}
