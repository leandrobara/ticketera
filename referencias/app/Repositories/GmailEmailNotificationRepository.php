<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Collection;
use App\Models\GmailEmailNotification;
use App\Repositories\Traits\VoidClearCache;
use App\DTO\GoogleAPI\GoogleAPIGmailMessageDTO;


class GmailEmailNotificationRepository implements Repository
{
    
    use VoidClearCache;


    public function findOneByClientUserAndGmailId(Client $client, User $user, string $gmailId): ?GmailEmailNotification
    {
        return GmailEmailNotification::query()
            ->where('user_id', $user->id)
            ->where('gmail_id', $gmailId)
            ->where('client_id', $client->id)
            ->first()
        ;
    }

    public function findNotViewedResponsesByUser(User $user, array $opts = []): Collection
    {
        $type = GmailEmailNotification::RESPONSE_TYPE;
        $queryBuilder = GmailEmailNotification::where('user_id', $user->id)
            ->where('client_id', $user->client_id)
            ->where('type', $type)
            ->where('is_notification_viewed', false)
            ->orderBy('id', 'desc')
        ;
        if ($opts['limit'] ?? null) {
            $queryBuilder->limit($opts['limit']);
        }
        return $queryBuilder->get();
    }


    public function createNewNotification(User $user, GoogleAPIGmailMessageDTO $gmailMsgDto): GmailEmailNotification
    {
        $type = $gmailMsgDto->isResponseToClientyUser
            ? GmailEmailNotification::RESPONSE_TYPE
            : GmailEmailNotification::SENT_TYPE
        ;
        $data = [
            'type' => $type,
            'user_id' => $user->id,
            'client_id' => $user->client_id,
            'is_notification_viewed' => false,
            'gmail_id' => $gmailMsgDto->gmailId,
            'email_subject' => $gmailMsgDto->subject,
            'email_sent_date' => $gmailMsgDto->sentDate,
            'email_name_from' => $gmailMsgDto->emailNameFrom,
            'email_address_from' => $gmailMsgDto->emailAddressFrom,
        ];
        return $this->create($data);
    }


    public function createNewNotificationIfNotExists(
        User $user,
        GoogleAPIGmailMessageDTO $gmailMsgDto
    ): GmailEmailNotification {
        $notification = $this->findOneByClientUserAndGmailId($user->client, $user, $gmailMsgDto->gmailId);
        
        if ($notification) {
            return $notification;
        }
        return $this->createNewNotification($user, $gmailMsgDto);
    }


    public function create(array $data): GmailEmailNotification
    {
        $gmailNotification = new GmailEmailNotification($data);
        $gmailNotification->saveOrFail();
        return $gmailNotification;
    }


    public function update(GmailEmailNotification $gmailNotification, array $data): GmailEmailNotification
    {
        $gmailNotification->fill($data);
        $gmailNotification->saveOrFail();
        return $gmailNotification->fresh();
    }

}
