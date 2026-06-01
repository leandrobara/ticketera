<?php

namespace App\Services\API;

use DateTime;
use Exception;
use DateTimeZone;
use App\Models\User;
use App\Models\Lead;
use App\Models\Client;
use App\Helpers\SimpleEncrypter;
use App\Services\API\LeadService;
use App\Models\AutomationNewLead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\LeadNotificationEmail;
use App\Helpers\ClientyMailerAPIHelper;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Services\Traits\StoresExistentInstance;
use App\Repositories\LeadNotificationEmailRepository;
use App\DTO\MailerQuickEmailScheduleRequestParametersDTO;


class LeadNotificationEmailService
{

    use GetClientFromRequest, GetUserFromRequest, StoresExistentInstance;

    private $leadService;
    private $notificationBccEmail;
    private $notificationFromEmail;
    private $clientyMailerAPIHelper;
    private $notificationsEmailEnabled;
    private $leadNotificationEmailRepository;


    public function findOrFail(int $id): LeadNotificationEmail
    {
        return $this->leadNotificationEmailRepository->findOrFail($id);
    }


    public function setLeadService(LeadService $leadService): LeadNotificationEmailService
    {
        $this->leadService = $leadService;
        return $this;
    }


    public function __construct(
        LeadNotificationEmailRepository $leadNotificationEmailRepository,
        ClientyMailerAPIHelper $clientyMailerAPIHelper,
        string $notificationsEmailEnabled,
        string $notificationFromEmail,
        string $notificationBccEmail
    ) {
        $this->notificationBccEmail = $notificationBccEmail;
        $this->notificationFromEmail = $notificationFromEmail;
        $this->clientyMailerAPIHelper = $clientyMailerAPIHelper;
        $this->notificationsEmailEnabled = $notificationsEmailEnabled;
        $this->leadNotificationEmailRepository = $leadNotificationEmailRepository;
    }


    public function createNewDefault(Lead $lead): LeadNotificationEmail
    {
        $data = [
            'reason' => 'new_lead',
            'lead_id' => $lead->id,
            'client_id' => $lead->client->id,
            'send_date' => new DateTime('now'),
        ];
        $enabledLeadAlert = $lead->client->clientSettings->enable_new_lead_email_alert;
        $disabled = !$this->notificationsEmailEnabled || !$enabledLeadAlert;
        if ($disabled) {
            $data['send_date'] = null;
            $data['do_not_send'] = true;
        }
        $notif = $this->create($data);
        return $notif;
    }


    public function createLeadUserChangeDefault(Lead $lead): LeadNotificationEmail
    {
        $data = [
            'lead_id' => $lead->id,
            'reason' => 'lead_user_change',
            'client_id' => $lead->client->id,
            'send_date' => new DateTime('now'),
        ];
        if (!$this->notificationsEmailEnabled) {
            $data['send_date'] = null;
            $data['do_not_send'] = true;
        }
        $notif = $this->create($data);
        return $notif;
    }


    public function findGroupedToSend(): Collection
    {
        $notifs = $this->leadNotificationEmailRepository->findGroupedToSend();
        return $notifs;
    }


    public function markToDoNotSendByAutomationNewLead(
        LeadNotificationEmail $notif,
        AutomationNewLead $automation
    ): LeadNotificationEmail {
        $data = [
            'send_date' => null,
            'is_grouped' => false,
            'do_not_send' => true,
            'automation_new_lead_id' => $automation->id,
        ];
        $notif = $this->update($notif, $data);
        return $notif;
    }


    public function markToSendGroupedByAutomationNewLead(
        LeadNotificationEmail $notif,
        AutomationNewLead $automation
    ): LeadNotificationEmail {
        // Set send date at 18 hs. (18 hs. at Client's timezone!)
        $tz = $automation->client->timezone;
        $baseDate = (new DateTime('now'))->setTimezone(new DateTimeZone($tz));
        if (intval($baseDate->format('H')) >= 18) {
            $baseDate->modify('+1 day');
        }
        $sendDate = (clone($baseDate))->setTime(18, 0, 0);
        // Convert client's TZ to UTC0
        $sendDate->setTimezone(new DateTimeZone('UTC'));

        $data = [
            'is_grouped' => true,
            'do_not_send' => false,
            'send_date' => $sendDate,
            'automation_new_lead_id' => $automation->id,
        ];
        $notif = $this->update($notif, $data);
        return $notif;
    }


    public function markAsSent(
        LeadNotificationEmail $notif,
        DateTime $sentDate,
        ?int $externalEmailId = null
    ): LeadNotificationEmail {
        $data = [
            'sent_date' => $sentDate,
            'external_email_id' => $externalEmailId,
        ];
        $notif = $this->update($notif, $data);
        return $notif;
    }


    public function markMultipleAsSent(
        Collection $notifications,
        DateTime $sentDate,
        ?int $externalEmailId = null
    ): Collection {
        $data = [
            'sent_date' => $sentDate,
            'external_email_id' => $externalEmailId,
        ];
        $updatedNotifs = $this->updateMultiple($notifications, $data);
        return $updatedNotifs;
    }


    public function create(array $data): LeadNotificationEmail
    {
        $leadNotificationEmail = $this->leadNotificationEmailRepository->create($data);
        return $leadNotificationEmail;
    }


    public function update(LeadNotificationEmail $leadNotificationEmail, array $data): LeadNotificationEmail
    {
        $updated = $this->leadNotificationEmailRepository->update($leadNotificationEmail, $data);
        return $updated;
    }


    public function updateMultiple(Collection $leadNotificationEmails, array $data): Collection
    {
        $updated = $this->leadNotificationEmailRepository->updateMultiple($leadNotificationEmails, $data);
        return $updated;
    }


    public function delete(LeadNotificationEmail $leadNotificationEmail): Note
    {
        $deleted = $this->leadNotificationEmailRepository->delete($leadNotificationEmail);
        return $deleted;
    }


    public function sendNewLeadNotificationEmailToLeadUser(Lead $lead): ?LeadNotificationEmail
    {
        $client = $lead->client;
        if (!$this->notificationsEmailEnabled) {
            return null;
        }
        $leadNotificationEmail = $lead->leadNotificationEmail;
        if (!$leadNotificationEmail || $leadNotificationEmail->do_not_send || $leadNotificationEmail->is_grouped) {
            return null;
        }

        $dateNow = new DateTime('now');
        $subject = "{$client->name} :: Nuevo prospecto - ID: {$lead->id}";
        
        $encryptedLeadId = SimpleEncrypter::encryptInt($lead->id);
        $directLeadUrl = clientUrl($client, "/?eli={$encryptedLeadId}");

        if ($lead->method === 'chat') {
            $body = view('api.emails.new-lead.chat', compact('lead', 'directLeadUrl'))->render();
        } else {
            $body = view('api.emails.new-lead.form', compact('lead', 'directLeadUrl'))->render();
        }

        $data = [
            'body' => $body,
            'subject' => $subject,
            'hasOpenTracking' => true,
            'fromName' => 'Clienty CRM',
            'to' => [$lead->user->email],
            'bcc' => [$this->notificationBccEmail],
            'from' => $this->notificationFromEmail,
            'appCustomId' => 'SYSTEM_new_lead_' . $lead->id,
            'sendDate' => $dateNow->format('Y-m-d\TH:i:sP'),
            'appCustomMetadata' => json_encode(['lead' => ['id' => $lead->id]]),
        ];
        if (redirectEmails()) {
            $data['to'] = [config('emails.redirect_emails_to')];
        }
        $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
        $mailerResponseDTO = $this->clientyMailerAPIHelper->scheduleQuickEmail($dto);

        $leadNotificationEmail = $this->update($leadNotificationEmail, [
            'external_email_id' => $mailerResponseDTO->id,
            'scheduled_date' => $dateNow->format('Y-m-d H:i:s'),
        ]);
        return $leadNotificationEmail;
    }


    public function sendLeadUserChangeNotificationEmailToLeadUser(Lead $lead, User $oldUser): ?LeadNotificationEmail
    {
        if (!$this->notificationsEmailEnabled) {
            return null;
        }

        $leadNotificationEmail = $lead->lastUserChangeLeadNotificationEmail;
        if (
            !$leadNotificationEmail ||
            $leadNotificationEmail->is_grouped ||
            $leadNotificationEmail->do_not_send ||
            $leadNotificationEmail->reason != 'lead_user_change'
        ) {
            return null;
        }

        $dateNow = new DateTime('now');
        $subject = "{$lead->client->name} :: Nueva asignación de prospecto - ID: {$lead->id}";
        
        $encryptedLeadId = SimpleEncrypter::encryptInt($lead->id);
        $directLeadUrl = clientUrl($lead->client, "/?eli={$encryptedLeadId}");

        if ($lead->method === 'chat') {
            $body = view('api.emails.lead-user-change.chat', compact('lead', 'directLeadUrl', 'oldUser'))->render();
        } else {
            $body = view('api.emails.lead-user-change.form', compact('lead', 'directLeadUrl', 'oldUser'))->render();
        }

        $data = [
            'body' => $body,
            'subject' => $subject,
            'hasOpenTracking' => true,
            'fromName' => 'Clienty CRM',
            'to' => [$lead->user->email],
            'bcc' => [$this->notificationBccEmail],
            'from' => $this->notificationFromEmail,
            'appCustomId' => 'SYSTEM_lead_user_change_' . $lead->id,
            'sendDate' => $dateNow->format('Y-m-d\TH:i:sP'),
            'appCustomMetadata' => json_encode([
                'lead' => ['id' => $lead->id],
                'old_user' => ['id' => $oldUser->id],
                'leadNotificationEmail' => ['id' => $leadNotificationEmail->id],
            ]),
        ];
        if (redirectEmails()) {
            $data['to'] = [config('emails.redirect_emails_to')];
        }
        $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
        $mailerResponseDTO = $this->clientyMailerAPIHelper->scheduleQuickEmail($dto);

        $leadNotificationEmail = $this->update($leadNotificationEmail, [
            'external_email_id' => $mailerResponseDTO->id,
            'scheduled_date' => $dateNow->format('Y-m-d H:i:s'),
        ]);
        return $leadNotificationEmail;
    }


    public function sendGroupedNewLeadNotificationEmailToLeadUsers(Collection $groupedNotifications): Collection
    {
        $processedLeadNotificationEmails = new Collection();

        if (!$this->notificationsEmailEnabled) {
            return $processedLeadNotificationEmails;
        }
        if ($groupedNotifications->isEmpty()) {
            return $processedLeadNotificationEmails;
        }

        $clientIds = $groupedNotifications->pluck('client_id')->unique();
        if ($clientIds->isEmpty()) {
            throw new Exception('no_client_in_notifications');
        }

        // Filtro de prevención
        $filteredNotifs = $groupedNotifications
            ->whereNull('sent_date')
            ->where('is_grouped', true)
            ->where('do_not_send', false)
            ->whereNull('scheduled_date')
            ->whereNotNull('automation_new_lead_id')
        ;
        if ($filteredNotifs->isEmpty()) {
            return collect([]);
        }

        $clientyUrl = config('app.url');
        $notifsGroupedByAutomationNewLead = $filteredNotifs->groupBy('automation_new_lead_id');
        foreach ($notifsGroupedByAutomationNewLead as $autNewLeadNotifs) {
            $client = $autNewLeadNotifs->first()->client;
            $automationNewLead = $autNewLeadNotifs->first()->automationNewLead;
            if (!$automationNewLead) {
                // @todo log this
                continue;
            }

            $notifsGroupedByUser = $autNewLeadNotifs->groupBy('lead.user_id');
            foreach ($notifsGroupedByUser as $userNewLeadNotifs) {
                $lead = null;
                $bodyParts = [];
                foreach ($userNewLeadNotifs as $notif) {
                    //Por si fue borrado
                    if (!$notif->lead || !$notif->lead->user) {
                        continue;
                    }

                    $lead = $notif->lead;
                    $encryptedLeadId = SimpleEncrypter::encryptInt($lead->id);
                    $directLeadUrl = clientUrl($client, "/?eli={$encryptedLeadId}");
                    $params = ['lead' => $lead, 'directLeadUrl' => $directLeadUrl];
                    if ($lead->method === 'chat') {
                        $bodyParts[] = view('api.emails.new-lead.grouped-chat-part', $params)->render();
                    } else {
                        $bodyParts[] = view('api.emails.new-lead.grouped-form-part', $params)->render();
                    }
                }

                if (!$lead || !$lead->user) {
                    continue;
                }

                $dateNow = new DateTime('now');
                $leadIds = $userNewLeadNotifs->pluck('lead_id');
                $subject = $automationNewLead->grouped_email_subject;
                $bodyMessage = $automationNewLead->grouped_email_body;
                $subject = $subject ?: "{$client->name} :: Prospectos agrupados del día {$dateNow->format('d/m')}";
                $body = view('api.emails.new-lead.grouped', compact('bodyParts', 'bodyMessage'))->render();
                $data = [
                    'body' => $body,
                    'subject' => $subject,
                    'hasOpenTracking' => true,
                    'fromName' => 'Clienty CRM',
                    'to' => [$lead->user->email],
                    'bcc' => [$this->notificationBccEmail],
                    'from' => $this->notificationFromEmail,
                    'appCustomId' => 'SYSTEM_GROUPED_new_leads',
                    'sendDate' => $dateNow->format('Y-m-d\TH:i:sP'),
                    'appCustomMetadata' => json_encode(['lead' => ['id' => $leadIds]]),
                ];
                if (redirectEmails()) {
                    $data['to'] = [config('emails.redirect_emails_to')];
                }

                $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
                $mailerResponseDTO = $this->clientyMailerAPIHelper->scheduleQuickEmail($dto);

                $leadNotificationEmails = $this->updateMultiple($userNewLeadNotifs, [
                    'external_email_id' => $mailerResponseDTO->id,
                    'scheduled_date' => $dateNow->format('Y-m-d H:i:s'),
                ]);
                $processedLeadNotificationEmails = $processedLeadNotificationEmails->merge($leadNotificationEmails);
            }
        }
        return $processedLeadNotificationEmails;
    }

}
