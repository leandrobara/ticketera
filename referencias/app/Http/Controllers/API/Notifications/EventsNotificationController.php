<?php

namespace App\Http\Controllers\API\Notifications;

use App\Models\Lead;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\Http\Requests\Notifications\WhatsAppMessageSentNotificationRequest;
use App\Http\Requests\Notifications\PhoneCallButtonClickedNotificationRequest;


class EventsNotificationController extends BaseAPIController
{

    public function whatsAppMessageSent(Lead $lead, WhatsAppMessageSentNotificationRequest $request)
    {
        $service = resolve(TimelineEventsDispatcherService::class);
        $service->whatsAppMessageSent($lead, $request->getPhoneNumber(), $request->getText());
        return $this->getSuccessResponse(['success' => true]);
    }


    public function phoneCallButtonClicked(Lead $lead, PhoneCallButtonClickedNotificationRequest $request)
    {
        $service = resolve(TimelineEventsDispatcherService::class);
        $service->phoneCallButtonClicked($lead, $request->getPhoneNumber());
        return $this->getSuccessResponse(['success' => true]);
    }

}
