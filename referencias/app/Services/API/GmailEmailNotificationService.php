<?php

namespace App\Services\API;

use DateTime;
use App\Models\User;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\GmailEmailNotification;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\DTO\GoogleAPI\GoogleAPIGmailMessageDTO;
use App\Repositories\GmailEmailNotificationRepository;


class GmailEmailNotificationService
{

    use GetClientFromRequest, GetUserFromRequest;

    private $gmailEmailNotificationRepository;


    public function __construct(Repository $gmailEmailNotificationRepository)
    {
        $this->gmailEmailNotificationRepository = $gmailEmailNotificationRepository;
    }


    public function findNotViewedResponsesByUser(User $user, array $opts = []): Collection
    {
        return $this->gmailEmailNotificationRepository->findNotViewedResponsesByUser($user, $opts);
    }


    // No uso transactions por que quien llama a este método ya implementa una.
    public function createNewNotifications(User $user, GoogleAPIGmailMessageDTO $gmailMsgDto): Collection
    {
        $notifications = new Collection();
        $gmailScopeIsClient = $user->client->clientSettings->google_gmail_api_scope == 'client';
        $usersToNotificate = collect([$user]);
        if ($gmailScopeIsClient) {
            $usersToNotificate = $user->client->users;
        }

        foreach ($usersToNotificate as $clientUser) {
            $gmailNotification = $this->gmailEmailNotificationRepository->createNewNotificationIfNotExists(
                $clientUser, $gmailMsgDto
            );
            $this->gmailEmailNotificationRepository->clearCacheForClient($clientUser->client_id);
            $notifications->push($gmailNotification);
        }
        return $notifications;
    }


    public function markAsViewed(GmailEmailNotification $gmailEmailNotification): GmailEmailNotification
    {
        $attrs = ['is_notification_viewed' => true];
        return $this->gmailEmailNotificationRepository->update($gmailEmailNotification, $attrs);
    }

}