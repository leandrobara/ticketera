<?php

namespace App\Http\Controllers\API\Notifications;

use App\Helpers\LockHelper;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Notifications\EmailNotificationService;
use App\Http\Requests\Notifications\EmailSentNotificationRequest;
use App\Http\Requests\Notifications\EmailOpenedNotificationRequest;
use App\Http\Requests\Notifications\EmailBouncedNotificationRequest;
use App\Http\Requests\Notifications\QuickEmailSentNotificationRequest;
use App\Http\Requests\Notifications\EmailComplainedNotificationRequest;
use App\Http\Requests\Notifications\EmailUnsubscribedNotificationRequest;


class EmailNotificationController extends BaseAPIController
{

    public function emailSent(EmailSentNotificationRequest $req, EmailNotificationService $service)
    {
        $lockIsGranted = resolve(LockHelper::class)->getLockByRequest($req, 5);
        $email = $lockIsGranted ? $service->handleSentEmailNotification($req->getValidatedDTO()) : null;
        return $this->getSuccessResponse(['email' => $email]);
    }


    public function quickEmailSent(QuickEmailSentNotificationRequest $req, EmailNotificationService $service)
    {
        $lockIsGranted = resolve(LockHelper::class)->getLockByRequest($req, 5);
        $quickEmail = $lockIsGranted ? $service->handleSentQuickEmailNotification($req->getValidatedDTO()) : null;
        return $this->getSuccessResponse(['quickEmail' => $quickEmail]);
    }


    public function emailOpened(EmailOpenedNotificationRequest $req, EmailNotificationService $service)
    {
        $email = null;
        $lockIsGranted = resolve(LockHelper::class)->getLockByRequest($req, 5);
        if ($lockIsGranted) {
            $service->handleOpenedEmailNotification($req->getValidatedDTO(), $req->getOpts());
        }
        return $this->getSuccessResponse(['email' => $email]);
    }


    public function emailBounced(EmailBouncedNotificationRequest $req, EmailNotificationService $service)
    {
        $lockIsGranted = resolve(LockHelper::class)->getLockByRequest($req, 5);
        $email = $lockIsGranted ? $service->handleBouncedEmailNotification($req->getValidatedDTO()) : null;
        return $this->getSuccessResponse(['email' => $email]);
    }


    public function emailComplained(EmailComplainedNotificationRequest $req, EmailNotificationService $service)
    {
        $lockIsGranted = resolve(LockHelper::class)->getLockByRequest($req, 5);
        $email = $lockIsGranted ? $service->handleComplainedEmailNotification($req->getValidatedDTO()) : null;
        return $this->getSuccessResponse(['email' => $email]);
    }


    public function emailUnsubscribed(EmailUnsubscribedNotificationRequest $req, EmailNotificationService $service)
    {
        $lockIsGranted = resolve(LockHelper::class)->getLockByRequest($req, 5);
        $email = $lockIsGranted ? $service->handleUnsubscribedEmailNotification($req->getValidatedDTO()) : null;
        return $this->getSuccessResponse(['email' => $email]);
    }

}