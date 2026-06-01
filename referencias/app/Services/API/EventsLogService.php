<?php

namespace App\Services\API;

use DateTime;
use Exception;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use App\Models\Email;
use App\Models\Status;
use App\Models\Client;
use App\Models\LeadSale;
use App\Models\AutomationTask;
use App\Models\MongoDB\EventLog;
use App\Models\AcquisitionChannel;
use Illuminate\Support\Collection;
use App\Models\WAutomationSequence;
use App\Models\AutomationEmailSend;
use App\Models\WhatsAppSendingMessage;
use App\Repositories\EventsLogRepository;
use App\Repositories\Criteria\Sort\EventLogs\SortByCreated;
use App\Repositories\Criteria\Filter\EventLogs\CreatedDateEndCriteria;
use App\Repositories\Criteria\Filter\EventLogs\CreatedDateStartCriteria;



class EventsLogService
{

    public function __construct(protected readonly EventsLogRepository $eventsLogRepository)
    {
    }


    public function list(Client $client, array $opts = []): Collection
    {
        $repoOpts = [
            'limit' => $opts['limit'] ?? 100,
            'fields' => $opts['fields'] ?? [],
            'order' => $this->getSortCriteriasByName($opts['order'] ?? ''),
            'filters' => $this->getFilterCriteriasByName($opts['filters'] ?? []),
        ];
        return $this->eventsLogRepository->list($client, $repoOpts);
    }


    public function findEventsFromOneLead(Lead $lead, array $events, array $opts = []): Collection
    {
        $repoOpts = [
            'order' => $this->getSortCriteriasByName($opts['order'] ?? ''),
        ];
        return $this->eventsLogRepository->findEventsFromOneLead($lead, $events, $repoOpts);
    }


    public function findEventsFromManyLeads(Collection $leadIds, array $events, array $opts = []): Collection
    {
        $repoOpts = [
            'order' => $this->getSortCriteriasByName($opts['order'] ?? ''),
        ];
        $eventLogs = $this->eventsLogRepository->findEventsFromManyLeads($leadIds, $events, $repoOpts);
        return $eventLogs;
    }


    public function findStatusOrTagChangeEventLogsByAutomation(
        AutomationTask | AutomationEmailSend | WAutomationSequence $automation,
        DateTime $dateStart,
        DateTime $dateEnd,
        array $opts = [],
    ): Collection {
        $limit = $opts['limit'] ?? 500;
        if (!$automation->isTagTriggered && !$automation->isStatusTriggered) {
            throw new Exception('automation_email_send_has_no_trigger_status_nor_tags');
        }
        
        $eventLogsFilters = [
            'date_end' => $dateEnd,
            'date_start' => $dateStart,
            'log.client_id' => $automation->client_id,
        ];
        if ($automation->isTagTriggered) {
            $eventLogsFilters['event'] = 'lead_tag_added';
            $eventLogsFilters['log.tag.id'] = $automation->triggeringTags->pluck('id')->toArray();
        }
        if ($automation->isStatusTriggered) {
            $eventLogsFilters['event'] = 'lead_status_updated';
            $eventLogsFilters['log.status.id'] = $automation->triggeringStatus->pluck('id')->toArray();
        }

        $listOpts = ['filters' => $eventLogsFilters, 'limit' => $limit];
        return $this->list($automation->client, $listOpts);
    }


    public function findUsersLifecycleEventLogsByClient(Client $client, array $opts = []): Collection
    {
        $opts['filters']['event'] = ['user_disabled', 'user_enabled', 'user_created'];
        $eventLogs = $this->list($client, $opts);
        return $eventLogs;
    }


    public function saveLeadSaleCreated(?User $user, LeadSale $leadSale, ?DateTime $eventLogDate = null): EventLog
    {
        $log = [
            'client_id' => $leadSale->client_id,
            'lead' => $this->getLeadData($leadSale->lead),
            'lead_sale' => $this->getLeadSaleData($leadSale),
            'user' => $user ? $this->getUserData($user) : 'SYSTEM',
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_sale_created', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadMassiveDeletion(
        User $user,
        array $leadIds,
        string $ip,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $log = [
            'ip' => $ip,
            'lead_ids' => $leadIds,
            'client_id' => $user->client_id,
            'user' => $this->getUserData($user),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_massive_deleted', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadSaleUpdated(
        ?User $user,
        array $oldLeadSale,
        User $oldLeadSaleUser,
        LeadSale $newLeadSale,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $log = [
            'client_id' => $newLeadSale->client_id,
            'lead' => $this->getLeadData($newLeadSale->lead),
            'lead_sale' => $this->getLeadSaleData($newLeadSale),
            'user' => $user ? $this->getUserData($user) : 'SYSTEM',
            'old_lead_sale' => $this->getOldLeadSaleData($oldLeadSale, $oldLeadSaleUser),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_sale_updated', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadSaleDeleted(?User $user, LeadSale $leadSale, ?DateTime $eventLogDate = null): EventLog
    {
        $log = [
            'lead' => $this->getLeadData($leadSale->lead),
            'lead_sale' => $this->getLeadSaleData($leadSale),
            'user' => $user ? $this->getUserData($user) : 'SYSTEM',
            'client_id' => $leadSale->client_id,
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_sale_deleted', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadTagAdded(?User $user, Tag $tag, Lead $lead, ?DateTime $eventLogDate = null): EventLog
    {
        $log = [
            'client_id' => $lead->client_id,
            'tag' => $this->getTagData($tag),
            'lead' => $this->getLeadData($lead),
            'user' => $user ? $this->getUserData($user) : 'SYSTEM',
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_tag_added', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadTagRemoved(?User $user, Lead $lead, Tag $tag, ?DateTime $eventLogDate = null): EventLog
    {
        $log = [
            'client_id' => $lead->client_id,
            'tag' => $this->getTagData($tag),
            'lead' => $this->getLeadData($lead),
            'user' => $user ? $this->getUserData($user) : 'SYSTEM',
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_tag_deleted', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadTaskAdded(?User $user, Task $task, ?DateTime $eventLogDate = null): EventLog
    {
        $log = [
            'client_id' => $task->client_id,
            'task' => $this->getTaskData($task),
            'lead' => $this->getLeadData($task->lead),
            'user' => $user ? $this->getUserData($user) : 'SYSTEM',
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_task_added', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadManuallyCreated(User $user, Lead $lead, ?DateTime $eventLogDate = null): EventLog
    {
        $log = [
            'client_id' => $lead->client_id,
            'lead' => $this->getLeadData($lead),
            'user' => $this->getUserData($user),
            'status' => $this->getStatusData($lead->status),
        ];
        $log['lead']['is_manually_created'] = true;
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_manually_created', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadCreated(Lead $lead, ?DateTime $eventLogDate = null): EventLog
    {
        $log = [
            'client_id' => $lead->client_id,
            'lead' => $this->getLeadData($lead),
            'status' => $this->getStatusData($lead->status),
        ];
        $log['lead']['is_manually_created'] = false;
        $log['lead']['user'] = $this->getUserData($lead->user);
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_created', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadTaskDeleted(User $user, Task $task, ?DateTime $eventLogDate = null): EventLog
    {
        $log = [
            'client_id' => $task->client_id,
            'user' => $this->getUserData($user),
            'task' => $this->getTaskData($task),
            'lead' => $this->getLeadData($task->lead),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_task_deleted', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadTaskCompleted(User $user, Task $task, ?DateTime $eventLogDate = null): EventLog
    {
        $log = [
            'client_id' => $task->client_id,
            'user' => $this->getUserData($user),
            'task' => $this->getTaskData($task),
            'lead' => $this->getLeadData($task->lead),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_task_completed', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadNoteAdded(?User $user, Note $note, ?DateTime $eventLogDate = null): EventLog
    {
        $log = [
            'client_id' => $note->client_id,
            'note' => $this->getNoteData($note),
            'lead' => $this->getLeadData($note->lead),
            'user' => $user ? $this->getUserData($user) : 'SYSTEM',
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_note_added', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadNoteUpdated(
        User $user,
        array $oldNote,
        Note $newNote,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $log = [
            'client_id' => $user->client_id,
            'user' => $this->getUserData($user),
            'note' => $this->getNoteData($newNote),
            'old_note' => $this->getNoteData($oldNote),
            'lead' => $this->getLeadData($newNote->lead),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_note_updated', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadNoteRemoved(User $user, Note $note, ?DateTime $eventLogDate = null): EventLog
    {
        $log = [
            'client_id' => $user->client_id,
            'user' => $this->getUserData($user),
            'note' => $this->getNoteData($note),
            'lead' => $this->getLeadData($note->lead),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_note_deleted', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadStatusUpdated(
        ?User $user,
        Lead $lead,
        Status $newStatus,
        array $oldStatus,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $log = [
            'client_id' => $lead->client_id,
            'lead' => $this->getLeadData($lead),
            'status' => $this->getStatusData($newStatus),
            'old_status' => $this->getStatusData($oldStatus),
            'user' => $user ? $this->getUserData($user) : 'SYSTEM',
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_status_updated', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadAcquisitionChannelUpdated(
        ?User $user,
        Lead $lead,
        AcquisitionChannel $newAcquisitionChannel,
        ?array $oldAcquisitionChannel,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $event = 'lead_acquisition_channel_updated';
        $log = [
            'client_id' => $lead->client_id,
            'lead' => $this->getLeadData($lead),
            'user' => $user ? $this->getUserData($user) : 'SYSTEM',
            'acquisition_channel' => $this->getAcquisitionChannelData($newAcquisitionChannel),
            'old_acquisition_channel' => $this->getAcquisitionChannelData($oldAcquisitionChannel),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_acquisition_channel_updated', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadQualityUpdated(
        ?User $user,
        Lead $lead,
        ?int $oldQuality,
        ?int $newQuality,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $log = [
            'old_quality' => $oldQuality,
            'new_quality' => $newQuality,
            'client_id' => $lead->client_id,
            'lead' => $this->getLeadData($lead),
            'user' => $user ? $this->getUserData($user) : 'SYSTEM',
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_quality_updated', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadUserUpdated(
        array $oldUser,
        User $newUser,
        Lead $lead,
        ?User $loginUser,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $log = [
            'client_id' => $lead->client_id,
            'lead' => $this->getLeadData($lead),
            'user' => $this->getUserData($newUser),
            'old_user' => $this->getUserData($oldUser),
            'event_user' => $loginUser ? $this->getUserData($loginUser) : 'SYSTEM',
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_user_updated', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadEmailScheduled(
        User $user,
        Email $email,
        Lead $lead,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $log = [
            'client_id' => $lead->client_id,
            'lead' => $this->getLeadData($lead),
            'user' => $this->getUserData($user),
            'email' => $this->getEmailData($email),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_email_scheduled', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadEmailSent(
        User $user,
        Email $email,
        Lead $lead,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $log = [
            'client_id' => $lead->client_id,
            'lead' => $this->getLeadData($lead),
            'user' => $this->getUserData($user),
            'email' => $this->getEmailData($email),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_email_sent', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }

    
    public function saveLeadEmailCancelled(
        User $user,
        Email $email,
        Lead $lead,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $log = [
            'client_id' => $lead->client_id,
            'lead' => $this->getLeadData($lead),
            'user' => $this->getUserData($user),
            'email' => $this->getEmailData($email),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_email_cancelled', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveWhatsAppMessageSent(
        User $user,
        Lead $lead,
        string $phoneNumber,
        ?string $text = null,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $log = [
            'text' => $text,
            'phoneNumber' => $phoneNumber,
            'client_id' => $lead->client_id,
            'lead' => $this->getLeadData($lead),
            'user' => $this->getUserData($user),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'whatsapp_message_sent', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveWhatsAppSendingMessageScheduledTimelineEvent(
        WhatsAppSendingMessage $whatsAppSendingMsg,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $log = [
            'client_id' => $whatsAppSendingMsg->client_id,
            'lead' => $this->getLeadData($whatsAppSendingMsg->lead),
            'user' => $this->getUserData($whatsAppSendingMsg->user),
            'whatsAppSendingMessage' => $this->getWhatsAppSendingMessageData($whatsAppSendingMsg),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'whatsapp_sending_message_scheduled', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveWhatsAppSendingMessageSentTimelineEvent(
        WhatsAppSendingMessage $whatsAppSendingMsg,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $log = [
            'client_id' => $whatsAppSendingMsg->client_id,
            'lead' => $this->getLeadData($whatsAppSendingMsg->lead),
            'user' => $this->getUserData($whatsAppSendingMsg->user),
            'whatsAppSendingMessage' => $this->getWhatsAppSendingMessageData($whatsAppSendingMsg),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'whatsapp_sending_message_sent', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function savePhoneCallButtonClicked(
        User $user,
        Lead $lead,
        string $phoneNumber,
        ?DateTime $eventLogDate = null
    ): EventLog {
        $log = [
            'phoneNumber' => $phoneNumber,
            'client_id' => $lead->client_id,
            'lead' => $this->getLeadData($lead),
            'user' => $this->getUserData($user),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'phone_call_button_clicked', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveLeadEmailOpened(Email $email, ?DateTime $eventLogDate = null): EventLog
    {
        $log = [
            'client_id' => $email->client_id,
            'email' => $this->getEmailData($email),
            'lead' => $this->getLeadData($email->lead),
        ];
        $log['email']['user'] = $this->getUserData($email->user);
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'lead_email_opened', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveUserCreated(
        User $loginUser,
        User $createdUser,
        DateTime $eventLogDate
    ): EventLog {
        $log = [
            'client_id' => $createdUser->client_id,
            'user' => $this->getUserData($loginUser),
            'created_user' => $this->getUserData($createdUser),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'user_created', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveUserEnabled(
        User $loginUser,
        User $enabledUser,
        DateTime $eventLogDate
    ): EventLog {
        $log = [
            'client_id' => $enabledUser->client_id,
            'user' => $this->getUserData($loginUser),
            'enabled_user' => $this->getUserData($enabledUser),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'user_enabled', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function saveUserDisabled(
        User $loginUser,
        User $disabledUser,
        DateTime $eventLogDate
    ): EventLog {
        $log = [
            'client_id' => $disabledUser->client_id,
            'user' => $this->getUserData($loginUser),
            'disabled_user' => $this->getUserData($disabledUser),
        ];
        $opts = ['eventLogDate' => $eventLogDate];
        $eventLogData = ['event' => 'user_disabled', 'log' => $log];
        return $this->eventsLogRepository->store($eventLogData, $opts);
    }


    public function findWhatsAppSendingMessageSentLogs(WhatsAppSendingMessage $wapMessage): Collection
    {
        return $this->eventsLogRepository->findWhatsAppSendingMessageSentLogs($wapMessage);
    }


    public function updateWhatsAppSendingMessageSuccess(EventLog $eventLog, bool $success): EventLog
    {
        return $this->eventsLogRepository->updateWhatsAppSendingMessageSuccess($eventLog, $success);
    }


    private function getTaskData($task): array
    {
        if (is_array($task)) {
            return [
                'id' => $task['id'],
                'title' => $task['title'],
                'status' => $task['status'],
                'description' => $task['description'],
                'limitDateTs' => (new DateTime($task['limit_date']))->getTimestamp(),
            ];
        }
        return [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'description' => $task->description,
            'user' => $this->getUserData($task->user),
            'limitDateTs' => $task->limit_date->getTimestamp(),
        ];
    }


    private function getTagData(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'text_color' => $tag->text_color,
            'background_color' => $tag->background_color,
        ];
    }


    private function getNoteData($note): array
    {
        if (is_array($note)) {
            return ['id' => $note['id'], 'text' => $note['text']];
        }
        return ['id' => $note->id, 'text' => $note->text];
    }


    private function getLeadData(Lead $lead): array
    {
        return  [
            'id' => $lead->id,
            'mainLeadContact' => $this->getLeadMainContact($lead->mainLeadContact),
        ];
    }


    private function getEmailData(Email $email): array
    {
        return  [
            'id' => $email->id,
            'to' => $email->leadContactEmail->email,
            'external_id' => $email->external_id,
            'external_custom_id' => $email->external_custom_id,
            'external_massive_id' => $email->external_massive_id,
            'external_custom_massive_id' => $email->external_custom_massive_id,
        ];
    }


    private function getLeadMainContact($mainLeadContact): array
    {
        return [
            'id' => $mainLeadContact->id,
            'name' => $mainLeadContact->name,
            'last_name' => $mainLeadContact->last_name,
        ];
    }


    private function getLeadSaleData(LeadSale $leadSale): array
    {
        return [
            'id' => $leadSale->id,
            'amount' => $leadSale->amount,
            'description' => $leadSale->description,
            'user' => $this->getUserData($leadSale->user),
            'is_manually_created' => $leadSale->is_manually_created,
            'sale_date' => $leadSale->sale_date->format('Y-m-d\TH:i:sP'),
        ];
    }


    private function getOldLeadSaleData(array $oldLeadSale, User $oldLeadSaleUser): array
    {
        return [
            'id' => $oldLeadSale['id'],
            'amount' => $oldLeadSale['amount'],
            'description' => $oldLeadSale['description'],
            'user' => $this->getUserData($oldLeadSaleUser),
            'is_manually_created' => $oldLeadSale['is_manually_created'],
        ];
    }


    private function getStatusData($status): array
    {
        if (is_array($status)) {
            return [
                'id' => $status['id'],
                'name' => $status['name'],
                'text_color' => $status['text_color'],
                'background_color' => $status['background_color'],
            ];
        }
        return [
            'id' => $status->id,
            'name' => $status->name,
            'text_color' => $status->text_color,
            'background_color' => $status->background_color,
        ];
    }


    private function getAcquisitionChannelData($channel): ?array
    {
        if (!$channel) {
            return null;
        }
        if (is_array($channel)) {
            return ['id' => $channel['id'], 'name' => $channel['name']];
        }
        return ['id' => $channel->id, 'name' => $channel->name];
    }


    private function getUserData($user): array
    {
        if (is_array($user)) {
            return [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'username' => $user['username'],
                'last_name' => $user['last_name'],
            ];
        }
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'last_name' => $user->last_name,
        ];
    }


    private function getWhatsAppSendingMessageData(WhatsAppSendingMessage $whatsAppSendingMsg): array
    {
        return [
            'id' => $whatsAppSendingMsg->id,
            'type' => $whatsAppSendingMsg->type,
            'success' => $whatsAppSendingMsg->success,
            'is_massive' => $whatsAppSendingMsg->is_massive,
            'is_proposal' => $whatsAppSendingMsg->is_proposal,
            'phone_number' => $whatsAppSendingMsg->phone_number,
            'send_attempts' => $whatsAppSendingMsg?->send_attempts ?? 1,
            'wautomation_log_id' => $whatsAppSendingMsg->wautomation_log_id,
            'whatsapp_sending_id' => $whatsAppSendingMsg->whatsapp_sending_id,
            'lead_contact_phone_id' => $whatsAppSendingMsg->lead_contact_phone_id,
            'send_date' => $whatsAppSendingMsg->send_date->format('Y-m-d\TH:i:sP'),
            'message' => $whatsAppSendingMsg->whatsAppSending->whatsAppSendingMessageText->message,
        ];
    }


    protected function getFilterCriteriasByName(array $filters): array
    {
        $nfilters = [];
        $criterias = [
            'date_end' => CreatedDateEndCriteria::class,
            'date_start' => CreatedDateStartCriteria::class,
        ];
        foreach ($filters as $key => $value) {
            if (in_array($key, array_keys($criterias)) && $value !== null) {
                $nfilters[$key] = new $criterias[$key]($value);
            } else {
                $nfilters[$key] = $value;
            }
        }
        return $nfilters;
    }


    private function getSortCriteriasByName($sortsName)
    {
        $sortTypes = [
            'created_date_asc' => new SortByCreated('asc'),
            'created_date_desc' => new SortByCreated('desc'),
        ];
        return $sortsName ? $sortTypes[$sortsName] : $sortsName;
    }

}
