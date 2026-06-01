<?php

namespace App\Http\Controllers\API\Actions;

use App\Models\GmailEmailNotification;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\GmailEmailNotificationService;
use App\Http\Requests\Actions\GmailEmailNotification\MarkGmailEmailNotificationAsViewedRequest;


class GmailEmailNotificationController extends BaseAPIController
{

    public function markAsViewed(
        GmailEmailNotification $gmailEmailNotification,
        MarkGmailEmailNotificationAsViewedRequest $req
    ) {
        $notif = resolve(GmailEmailNotificationService::class)->markAsViewed($gmailEmailNotification);
        return $this->getSuccessResponse($notif);
    }

}
