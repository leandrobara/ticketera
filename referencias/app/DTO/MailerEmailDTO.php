<?php

namespace App\DTO;

use Illuminate\Support\Str;

class MailerEmailDTO
{
    const UNDEFINED = '__UNDEFINED__';

    private $id = self::UNDEFINED;
    private $appId = self::UNDEFINED;
    private $senderId = self::UNDEFINED;
    private $subject = self::UNDEFINED;
    private $emailSubjectId = self::UNDEFINED;
    private $body = self::UNDEFINED;
    private $emailBodyId = self::UNDEFINED;
    private $emailAddressToId = self::UNDEFINED;
    private $emailAddressFromId = self::UNDEFINED;
    private $massiveSendingId = self::UNDEFINED;
    private $senderExternalId = self::UNDEFINED;
    private $openTrackingGuid = self::UNDEFINED;
    private $appCustomId = self::UNDEFINED;
    private $appCustomMassiveId = self::UNDEFINED;
    private $appCustomMetadata = self::UNDEFINED;
    private $fromName = self::UNDEFINED;
    private $cc = self::UNDEFINED;
    private $bcc = self::UNDEFINED;
    private $variables = self::UNDEFINED;
    private $attachments = self::UNDEFINED;
    private $hasLinkTracking = self::UNDEFINED;
    private $openedIp = self::UNDEFINED;
    private $openedDate = self::UNDEFINED;
    private $sendDate = self::UNDEFINED;
    private $sentDate = self::UNDEFINED;
    private $bouncedDate = self::UNDEFINED;
    private $complainedDate = self::UNDEFINED;
    private $unsubscribedDate = self::UNDEFINED;
    private $createdAt = self::UNDEFINED;
    private $cancelledAt = self::UNDEFINED;
    private $updatedAt = self::UNDEFINED;
    private $deletedAt = self::UNDEFINED;


    public static function buildFromEmail(array $mailerEmail): MailerEmailDTO
    {
        $dto = new MailerEmailDTO();
        foreach ($mailerEmail as $k => $v) {
            $attr = Str::of($k)->camel();
            if ($attr == 'bouncedAt') {
                $attr = 'bouncedDate';
            }
            if ($attr == 'openedAt') {
                $attr = 'openedDate';
            }
            if ($attr == 'unsubscribedAt') {
                $attr = 'unsubscribedDate';
            }
            if ($attr == 'complainedAt') {
                $attr = 'complainedDate';
            }
            if ($attr == 'cancelledAt') {
                $attr = 'cancelledDate';
            }
            if ($attr == 'sendAt') {
                $attr = 'sendDate';
            }
            if ($attr == 'sentAt') {
                $attr = 'sentDate';
            }
            $dto->{$attr} = $v;
        }

        return $dto;
    }


    public function get(string $propName)
    {
        if (!property_exists($this, $propName)) {
            return null;
        }
        return $this->$propName !== self::UNDEFINED ? $this->$propName : null;
    }


    public function toArray(): array
    {
        $arr = [];
        if ($this->id !== self::UNDEFINED) {
            $arr['id'] = $this->id;
        }
        if ($this->appId !== self::UNDEFINED) {
            $arr['app_id'] = $this->appId;
        }
        if ($this->senderId !== self::UNDEFINED) {
            $arr['sender_id'] = $this->senderId;
        }
        if ($this->subject !== self::UNDEFINED) {
            $arr['subject'] = $this->subject;
        }
        if ($this->emailSubjectId !== self::UNDEFINED) {
            $arr['email_subject_id'] = $this->emailSubjectId;
        }
        if ($this->emailBodyId !== self::UNDEFINED) {
            $arr['email_body_id'] = $this->emailBodyId;
        }
        if ($this->body !== self::UNDEFINED) {
            $arr['body'] = $this->body;
        }
        if ($this->emailAddressToId !== self::UNDEFINED) {
            $arr['email_address_to_id'] = $this->emailAddressToId;
        }
        if ($this->emailAddressFromId !== self::UNDEFINED) {
            $arr['email_address_from_id'] = $this->emailAddressFromId;
        }
        if ($this->massiveSendingId !== self::UNDEFINED) {
            $arr['massive_sending_id'] = $this->massiveSendingId;
        }
        if ($this->senderExternalId !== self::UNDEFINED) {
            $arr['sender_external_id'] = $this->senderExternalId;
        }
        if ($this->openTrackingGuid !== self::UNDEFINED) {
            $arr['open_tracking_guid'] = $this->openTrackingGuid;
        }
        if ($this->appCustomId !== self::UNDEFINED) {
            $arr['app_custom_id'] = $this->appCustomId;
        }
        if ($this->appCustomMassiveId !== self::UNDEFINED) {
            $arr['app_custom_massive_id'] = $this->appCustomMassiveId;
        }
        if ($this->appCustomMetadata !== self::UNDEFINED) {
            $arr['app_custom_metadata'] = $this->appCustomMetadata;
        }
        if ($this->fromName !== self::UNDEFINED) {
            $arr['from_name'] = $this->fromName;
        }
        if ($this->cc !== self::UNDEFINED) {
            $arr['cc'] = $this->cc;
        }
        if ($this->bcc !== self::UNDEFINED) {
            $arr['bcc'] = $this->bcc;
        }
        if ($this->variables !== self::UNDEFINED) {
            $arr['variables'] = $this->variables;
        }
        if ($this->variables !== self::UNDEFINED) {
            $arr['attachments'] = $this->attachments;
        }
        if ($this->hasLinkTracking !== self::UNDEFINED) {
            $arr['has_link_tracking'] = $this->hasLinkTracking;
        }
        if ($this->openedIp !== self::UNDEFINED) {
            $arr['opened_ip'] = $this->openedIp;
        }
        if ($this->openedDate !== self::UNDEFINED) {
            $arr['opened_date'] = $this->openedDate;
        }
        if ($this->sendDate !== self::UNDEFINED) {
            $arr['send_date'] = $this->sendDate;
        }
        if ($this->sentDate !== self::UNDEFINED) {
            $arr['sent_date'] = $this->sentDate;
        }
        if ($this->bouncedDate !== self::UNDEFINED) {
            $arr['bounced_date'] = $this->bouncedDate;
        }
        if ($this->complainedDate !== self::UNDEFINED) {
            $arr['complained_date'] = $this->complainedDate;
        }
        if ($this->cancelledDate !== self::UNDEFINED) {
            $arr['cancelled_date'] = $this->cancelledDate;
        }
        if ($this->unsubscribedDate !== self::UNDEFINED) {
            $arr['unsubscribed_date'] = $this->unsubscribedDate;
        }
        if ($this->createdAt !== self::UNDEFINED) {
            $arr['created_at'] = $this->createdAt;
        }
        if ($this->updatedAt !== self::UNDEFINED) {
            $arr['updated_at'] = $this->updatedAt;
        }
        if ($this->deletedAt !== self::UNDEFINED) {
            $arr['deleted_at'] = $this->deletedAt;
        }

        return $arr;
    }
}
