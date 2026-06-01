<?php

namespace App\Http\Controllers\API\Actions;

use App\Models\NewsNotification;
use App\Services\API\NewsNotificationService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Actions\MarkNewsNotificationAsViewedRequest;


class NewsNotificationController extends BaseAPIController
{

    public function markAsViewed(NewsNotification $newsNotification, MarkNewsNotificationAsViewedRequest $request)
    {
        $newsNotification = resolve(NewsNotificationService::class)->markAsViewed($newsNotification);
        return $this->getSuccessResponse($newsNotification);
    }

}
