<?php

namespace App\Http\Controllers\API\Views;

use App\Services\API\NPSPollService;
use App\Services\API\Views\TaskService;
use App\Services\API\NewsNotificationService;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\GmailEmailNotificationService;
use App\Http\Requests\Views\ListMyNotificationsRequest;
use App\Services\API\Notifications\NotificationService;
use App\Http\Resources\Views\TaskNotification\TaskNotificationResourceCollection;
use App\Http\Resources\Views\NewsNotification\NewsNotificationResourceCollection;
use App\Http\Resources\Views\GmailEmailNotification\GmailEmailNotificationResourceCollection;


class UserController extends BaseAPIController
{

    public function listMyNotifications(ListMyNotificationsRequest $req)
    {
        $NPSPoll = resolve(NPSPollService::class)->findCurrentUnscoredByUser($req->user);
        $newsToNotify = resolve(NewsNotificationService::class)->findNotViewedByUser($req->user);
        $notifications = resolve(NotificationService::class)->listClientNotificationsByUser($req->user);
        $tasksToNotify = resolve(TaskService::class)->findTasksToNotify($req->user, $req->getValidatedTasksParams());
        $gmailNotifications = resolve(GmailEmailNotificationService::class)->findNotViewedResponsesByUser(
            $req->user, $req->getValidatedGmailParams()
        );

        return $this->getSuccessResponse([
            'NPSPoll' => $NPSPoll,
            'notifications' => $notifications,
            'showNonPaymentAlert' => $req->client->clientSettings->show_non_payment_alert,
            'newsNotifications' => new NewsNotificationResourceCollection($newsToNotify),
            'taskNotifications' => new TaskNotificationResourceCollection($tasksToNotify),
            'gmailEmailNotifications' => new GmailEmailNotificationResourceCollection($gmailNotifications),
        ]);
    }

}
