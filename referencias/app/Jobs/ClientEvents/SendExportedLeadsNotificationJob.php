<?php

namespace App\Jobs\ClientEvents;

use DateTime;
use Throwable;
use DateTimeZone;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use App\Services\API\TagService;
use App\Services\API\UserService;
use App\Models\ClientInteraction;
use App\Services\API\StatusService;
use Illuminate\Queue\SerializesModels;
use App\Helpers\ClientyMailerAPIHelper;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Jobs\ClientEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\DTO\MailerQuickEmailScheduleRequestParametersDTO;


class SendExportedLeadsNotificationJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;


    public function __construct(
        protected readonly int $userId,
        protected readonly ?string $userIp,
        protected readonly array $exportRawFilters,
        protected readonly int $exportedLeadsCount,
    ) {
    }


    public function handle()
    {
        $user = resolve(UserService::class)->findOrFail($this->userId);
        $adminUsers = $user->client->users->where('type', 'admin')->where('enabled', true);
        $adminUsersEnabled = $adminUsers->where('enabled_export_leads_emails_reception', true);
        $toAddresses = $adminUsersEnabled->pluck('email')->unique()->values()->toArray();
        if (!$toAddresses) {
            return true;
        }
        
        $viewData = [
            'user' => $user,
            'userIp' => $this->userIp,
            'exportedLeadsCount' => $this->exportedLeadsCount,
            'filtersLegends' => $this->getExportFiltersLegends($user->client, $this->exportRawFilters),
        ];
        $fromAddress = config('emails.leads_notification_from_email');
        $subject = "Clienty CRM | Aviso de exportación de prospectos";
        $body = view('api.emails.exported-leads-notification.body', $viewData)->render();
        
        $data = [
            'body' => $body,
            'to' => $toAddresses,
            'subject' => $subject,
            'from' => $fromAddress,
            'fromName' => 'Clienty CRM',
            'appCustomId' => 'SYSTEM_exported_leads_notification',
            'sendDate' => (new DateTime())->format('Y-m-d\TH:i:sP'),
            'appCustomMetadata' => json_encode([
                'user_id' => $user->id,
                'exportRawFilters' => $this->exportRawFilters,
            ]),
        ];
        if (redirectEmails()) {
            $data['to'] = [config('emails.redirect_emails_to')];
        }

        $dto = MailerQuickEmailScheduleRequestParametersDTO::buildFromArray($data);
        $mailerResponseDTO = resolve(ClientyMailerAPIHelper::class)->scheduleQuickEmail($dto);
    }


    protected function getExportFiltersLegends(Client $client, array $exportRawFilters): array
    {
        $filtersLegends = [];
        if ($exportRawFilters['id'] ?? null) {
            $leadIdsStr = implode(', ', $exportRawFilters['id']);
            $filtersLegends[] = ['title' => 'IDs de prospecto', 'value' => $leadIdsStr];
        }
        if ($exportRawFilters['tag_id'] ?? null) {
            $tagIds = $exportRawFilters['tag_id'];
            $tagIds = is_array($tagIds) ? $tagIds : [$tagIds];
            $tags = resolve(TagService::class)->findByClientAndIds($client, $tagIds);
            $tagNamesStr = $tags->pluck('name')->implode(' | ');
            $filtersLegends[] = ['title' => 'Etiquetas', 'value' => $tagNamesStr];
        }
        if ($exportRawFilters['user_id'] ?? null) {
            $userIds = $exportRawFilters['user_id'];
            $userIds = is_array($userIds) ? $userIds : [$userIds];
            $userList = resolve(UserService::class)->findByClientAndIds($client, $userIds);
            $userNamesStr = $userList->map(fn ($u) => "{$u->name} {$u->last_name}")->implode(' | ');
            $filtersLegends[] = ['title' => 'Asignados a usuarios', 'value' => $userNamesStr];
        }
        if ($exportRawFilters['status_id'] ?? null) {
            $statusIds = $exportRawFilters['status_id'];
            $statusIds = is_array($statusIds) ? $statusIds : [$statusIds];
            $statusList = resolve(StatusService::class)->findByClientAndIds($client, $statusIds);
            $statusNamesStr = $statusList->pluck('name')->implode(' | ');
            $filtersLegends[] = ['title' => 'Estados', 'value' => $statusNamesStr];
        }
        if ($exportRawFilters['created_date_start'] ?? null) {
            $clientTz = new DateTimeZone($client->timezone);
            $dateStart = (new DateTime($exportRawFilters['created_date_start']))->setTimezone($clientTz);
            $dateStartStr = $dateStart->format('d/m/Y');
            $filtersLegends[] = ['title' => 'Fecha desde', 'value' => $dateStartStr];
        }
        if ($exportRawFilters['created_date_end'] ?? null) {
            $clientTz = new DateTimeZone($client->timezone);
            $dateEnd = (new DateTime($exportRawFilters['created_date_end']))->setTimezone($clientTz);
            $dateEndStr = $dateEnd->format('d/m/Y');
            $filtersLegends[] = ['title' => 'Fecha hasta', 'value' => $dateEndStr];
        }
        return $filtersLegends;
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
