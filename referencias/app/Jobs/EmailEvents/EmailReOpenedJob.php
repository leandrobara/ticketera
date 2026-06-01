<?php

namespace App\Jobs\EmailEvents;

use DateTime;
use Throwable;
use DateTimeZone;
use Carbon\Carbon;
use App\Models\Email;
use App\Helpers\LockHelper;
use App\Models\AutomationLog;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use Illuminate\Queue\SerializesModels;
use App\Helpers\ClientyMailerAPIHelper;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\EmailEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\DTO\MailerQuickEmailScheduleRequestParametersDTO;
use App\Services\API\Dispatchers\BrowserEventsDispatcher;
use App\Services\API\Notifications\EmailNotificationService;
use App\Services\API\Views\EmailService as ViewsEmailService;
use App\Services\API\Automations\AutomationProposalInteractionService;


class EmailReOpenedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    public $reOpenedEmailId;


    public function __construct(int $reOpenedEmailId)
    {
        $this->reOpenedEmailId = $reOpenedEmailId;
    }


    public function handle()
    {
        // To avoid race condition
        usleep(mt_rand(70000, 200000));
        $key = 'EmailReOpenedJob:handle:reOpenedEmailId:' . $this->reOpenedEmailId;
        $lockIsGranted = resolve(LockHelper::class)->getLockByName($key, 3);
        if (!$lockIsGranted) {
            return null;
        }
        
        $reOpenedEmail = Email::findOrFail($this->reOpenedEmailId);
        if (!$reOpenedEmail->is_proposal || !$reOpenedEmail->sent_date || !$reOpenedEmail->opened_date) {
            return;
        }
        if (!$reOpenedEmail->lead || !$reOpenedEmail->user || !$reOpenedEmail->client->enabled) {
            return;
        }

        $this->sendReOpenedProposalEmailNotificationWhenApplicable($reOpenedEmail);
        $this->sendReOpenedProposalBrowserNotificationWhenApplicable($reOpenedEmail);
    }


    protected function sendReOpenedProposalEmailNotificationWhenApplicable(Email $reOpenedEmail): void
    {
        $emailNotificationService = resolve(EmailNotificationService::class);
        $lastAlertNotification = $emailNotificationService->findLastOpenLogWithReopenedProposalEmailNotification(
            $reOpenedEmail
        );
        if ($lastAlertNotification) {
            return;
        }

        $clientSettings = $reOpenedEmail->client->clientSettings;
        $emailAlertEnabled = $clientSettings->enable_sent_proposal_reopened_email_alert;
        $reopenedProposalAlertMinDays = $clientSettings->sent_proposal_reopened_email_alert_min_days;
        if (!$emailAlertEnabled || !$reopenedProposalAlertMinDays) {
            return;
        }
    
        $dateNow = Carbon::now();
        $proposalSentDate = Carbon::parse($reOpenedEmail->sent_date);
        $daysSinceReopening = $proposalSentDate->diffInDays($dateNow);
        if ($daysSinceReopening < $reopenedProposalAlertMinDays) {
            return;
        }

        $this->sendQuickEmailNotification($reOpenedEmail);
        $emailNotificationService->markLastOpenLogWithReopenedProposalEmailNotified($reOpenedEmail);
    }


    protected function sendReOpenedProposalBrowserNotificationWhenApplicable(Email $reOpenedEmail): void
    {
        $emailNotificationService = resolve(EmailNotificationService::class);
        $lastAlertNotification = $emailNotificationService->findLastOpenLogWithReopenedProposalBrowserNotification(
            $reOpenedEmail
        );
        if ($lastAlertNotification) {
            return;
        }

        $clientSettings = $reOpenedEmail->client->clientSettings;
        $browserAlertEnabled = $clientSettings->enable_sent_proposal_reopened_browser_alert;
        $reopenedProposalAlertMinDays = $clientSettings->sent_proposal_reopened_browser_alert_min_days;
        if (!$browserAlertEnabled || !$reopenedProposalAlertMinDays) {
            return;
        }

        $dateNow = Carbon::now();
        $proposalSentDate = Carbon::parse($reOpenedEmail->sent_date);
        $daysSinceReopening = $proposalSentDate->diffInDays($dateNow);
        if ($daysSinceReopening < $reopenedProposalAlertMinDays) {
            return;
        }

        resolve(BrowserEventsDispatcher::class)->notifyReOpenedProposal($reOpenedEmail);
        $emailNotificationService->markLastOpenLogWithReopenedProposalBrowserNotified($reOpenedEmail);
    }


    protected function sendQuickEmailNotification(Email $reOpenedEmail): void
    {
        $client = $reOpenedEmail->client;
        $dateTimeZone = new DateTimeZone($client->timezone);
        $dateNow = (new DateTime('now'))->setTimezone($dateTimeZone);
        $proposalSentDate = $reOpenedEmail->sent_date->setTimezone($dateTimeZone);
        $firstOpenedDate = $reOpenedEmail->opened_date->setTimezone($dateTimeZone);
        $reOpenedEmail = resolve(ViewsEmailService::class)->fillEmailWithMailerInfo($reOpenedEmail);
        $daysSinceReopening = $dateNow->diff($proposalSentDate)->format("%a");

        $viewData = [
            'lead' => $reOpenedEmail->lead,
            'user' => $reOpenedEmail->user,
            'daysSinceReopening' => $daysSinceReopening,
            'reOpenedEmailReopenedDateStr' => $dateNow->format('d/m/Y'),
            'reOpenedEmailSentDateStr' => $proposalSentDate->format('d/m/Y'),
            'reOpenedEmailFirstOpenedDateStr' => $firstOpenedDate->format('d/m/Y'),
            'reOpenedEmailSubject' => $reOpenedEmail->getMailerDTO()->get('subject'),
            'reopenedEmailAlertMinDays' => $client->clientSettings->sent_proposal_reopened_email_alert_min_days,
        ];
        $body = view('api.emails.proposal-notification.reopened-alert', $viewData)->render();

        $subject = "Clienty CRM | Un presupuesto fue reabierto luego de {$daysSinceReopening} días";
        $quickEmailData = [
            'body' => $body,
            'subject' => $subject,
            'hasOpenTracking' => false,
            'fromName' => 'Clienty CRM',
            'to' => [$reOpenedEmail->user->email],
            'sendDate' => Carbon::now()->format('Y-m-d\TH:i:sP'),
            'from' => config('emails.leads_notification_from_email'),
            'appCustomId' => 'SYSTEM_reopened_proposal_notification_' . $reOpenedEmail->id,
            'appCustomMetadata' => json_encode(['reOpenedEmail' => ['id' => $reOpenedEmail->id]]),
        ];
        if (redirectEmails()) {
            $quickEmailData['to'] = [config('emails.redirect_emails_to')];
        }

        $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($quickEmailData);
        $mailerResponseDTO = resolve(ClientyMailerAPIHelper::class)->scheduleQuickEmail($dto);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
        $this->getErrorLog()->error(PHP_EOL . PHP_EOL);
    }

}
