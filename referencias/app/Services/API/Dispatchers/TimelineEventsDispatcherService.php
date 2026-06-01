<?php

namespace App\Services\API\Dispatchers;

use DateTime;
use App\Models\Note;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Models\Email;
use App\Models\Status;
use App\Models\LeadSale;
use App\Models\WhatsAppSending;
use App\Models\AcquisitionChannel;
use Illuminate\Support\Collection;
use App\Models\WhatsAppSendingMessage;
use App\Services\Traits\CustomDispatch;
use App\Services\Traits\GetUserFromRequest;
use Illuminate\Foundation\Bus\PendingDispatch;
use App\Jobs\TimelineEvents\SaveLeadCreatedJob;
use App\Jobs\TimelineEvents\SaveUserCreatedJob;
use App\Jobs\TimelineEvents\SaveUserEnabledJob;
use App\Jobs\TimelineEvents\SaveUserDisabledJob;
use App\Jobs\TimelineEvents\SaveLeadTagAddedJob;
use App\Jobs\TimelineEvents\SaveLeadTaskAddedJob;
use App\Jobs\TimelineEvents\SaveLeadNoteAddedJob;
use App\Jobs\TimelineEvents\SaveLeadEmailSentJob;
use App\Jobs\TimelineEvents\SaveLeadTagRemovedJob;
use App\Jobs\TimelineEvents\SaveLeadEmailOpenedJob;
use App\Jobs\TimelineEvents\SaveLeadTaskDeletedJob;
use App\Jobs\TimelineEvents\SaveLeadNoteRemovedJob;
use App\Jobs\TimelineEvents\SaveLeadNoteUpdatedJob;
use App\Jobs\TimelineEvents\SaveLeadSaleUpdatedJob;
use App\Jobs\TimelineEvents\SaveLeadSaleCreatedJob;
use App\Jobs\TimelineEvents\SaveLeadSaleDeletedJob;
use App\Jobs\TimelineEvents\SaveLeadUserUpdatedJob;
use App\Jobs\TimelineEvents\SaveLeadStatusUpdatedJob;
use App\Jobs\TimelineEvents\SaveLeadTaskCompletedJob;
use App\Jobs\TimelineEvents\SaveLeadMassiveDeletedJob;
use App\Jobs\TimelineEvents\SaveLeadQualityUpdatedJob;
use App\Jobs\TimelineEvents\SaveLeadEmailScheduledJob;
use App\Jobs\TimelineEvents\SaveLeadEmailCancelledJob;
use App\Jobs\TimelineEvents\SaveLeadManuallyCreatedJob;
use App\Jobs\TimelineEvents\SaveWhatsAppMessageSentJob;
use App\Jobs\TimelineEvents\SaveMassiveEmailCancelledJob;
use App\Jobs\TimelineEvents\SavePhoneCallButtonClickedJob;
use App\Jobs\TimelineEvents\SaveMultipleLeadUserUpdatedJob;
use App\Jobs\TimelineEvents\SaveLeadAcquisitionChannelUpdatedJob;
use App\Exceptions\Services\Traits\GetUserFromRequestTraitException;
use App\Jobs\TimelineEvents\SaveWhatsAppSendingMessageSentTimelineEventJob;
use App\Jobs\TimelineEvents\SaveWhatsAppSendingMessageScheduledTimelineEventJob;
use App\Exceptions\Services\TimelineEventsDispatcher\TimelineEventsDispatcherException;


class TimelineEventsDispatcherService
{

    use GetUserFromRequest, CustomDispatch;

    private $loginUser;
    private $eventQueueName;
    private $queueConnection;
    private bool $isLoginUserInitialized = false;

    

    // @todo
    // Este método se usa por que al usar un serviceX desde un request, puede darse que este service, como parte
    // de una composición de serviceX, se inicialice ANTES de que se inyecte USER en el request.
    // Hay que resolver un poco todo este acoplamiento.
    protected function initializeLoginUser(): void
    {
        if ($this->isLoginUserInitialized) {
            return;
        }
        try {
            $this->loginUser = $loginUser ?? $this->getUser();
        } catch (GetUserFromRequestTraitException $e) {
            $this->loginUser = null;
        } finally {
            $this->isLoginUserInitialized = true;
        }
    }

    public function setLoginUser(?User $user): TimelineEventsDispatcherService
    {
        $this->loginUser = $user;
        $this->isLoginUserInitialized = true;
        return $this;
    }


    protected function getLoginUserId(): ?int
    {
        return $this->loginUser ? $this->loginUser->id : null;
    }


    public function __construct(
        string $eventQueueName,
        string $queueConnection,
        ?User $loginUser = null
    ) {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
        // try {
        //     $this->loginUser = $loginUser ?? $this->getUser();
        // } catch (GetUserFromRequestTraitException $e) {
        //     $this->loginUser = null;
        // }
    }


    public function leadNoteCreated(Note $note)
    {
        $this->initializeLoginUser();
        $params = [$this->getLoginUserId(), $note->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadNoteAddedJob::class, $params, null, $note->client_id);
    }


    public function leadNoteUpdated(array $oldNote, Note $newNote)
    {
        $this->initializeLoginUser();
        $params = [$this->getLoginUserId(), $oldNote, $newNote->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadNoteUpdatedJob::class, $params, null, $newNote->client_id);
    }


    public function leadNoteDeleted(Note $note)
    {
        $this->initializeLoginUser();
        $params = [$this->getLoginUserId(), $note->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadNoteRemovedJob::class, $params, null, $note->client_id);
    }


    public function leadSaleCreated(LeadSale $leadSale)
    {
        $this->initializeLoginUser();
        $params = [$this->getLoginUserId(), $leadSale->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadSaleCreatedJob::class, $params, null, $leadSale->client_id);
    }


    public function leadSaleUpdated(array $oldLeadSale, User $oldLeadSaleUser, LeadSale $newLeadSale)
    {
        $this->initializeLoginUser();
        $dateStr = $this->getCurrentDate();
        $params = [$this->getLoginUserId(), $oldLeadSale, $oldLeadSaleUser->id, $newLeadSale->id, $dateStr];
        $this->doCustomDispatch(SaveLeadSaleUpdatedJob::class, $params, null, $newLeadSale->client_id);

    }


    public function leadSaleDeleted(LeadSale $leadSale)
    {
        $params = [$leadSale->userId, $leadSale->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadSaleDeletedJob::class, $params, null, $leadSale->client_id);
    }


    public function leadTagsUpdated(
        Lead $lead,
        Collection $prevLeadTagIds,
        Collection $leadTagsAfterChange,
        string $assignType = 'add'
    ) {
        $this->initializeLoginUser();
        $clientId = $lead->client_id;
        $loginUserId = $this->getLoginUserId();
        $currentDateString = $this->getCurrentDate();
        $leadTagIdsAfterChange = $leadTagsAfterChange->pluck('id');

        if ($assignType == 'add') {
            $existentTagIds = $prevLeadTagIds->intersect($leadTagIdsAfterChange);
            $addedTagIds = $leadTagIdsAfterChange->diff($existentTagIds)->values();
            foreach ($addedTagIds as $addedTagId) {
                $params = [$loginUserId, $lead->id, $addedTagId, $currentDateString];
                $this->doCustomDispatch(SaveLeadTagAddedJob::class, $params, null, $clientId);
            }
        }
        
        if ($assignType == 'remove') {
            $removedTagIds = $prevLeadTagIds->intersect($leadTagIdsAfterChange)->values();
            foreach ($removedTagIds as $removedTagId) {
                $params = [$loginUserId, $lead->id, $removedTagId, $currentDateString];
                $this->doCustomDispatch(SaveLeadTagRemovedJob::class, $params, null, $clientId);
            }
        }

        if ($assignType == 'replace') {
            $removedTagIds = $prevLeadTagIds->diff($leadTagIdsAfterChange)->values();
            foreach ($removedTagIds as $removedTagId) {
                $params = [$loginUserId, $lead->id, $removedTagId, $currentDateString];
                $this->doCustomDispatch(SaveLeadTagRemovedJob::class, $params, null, $clientId);
            }

            $existentTagIds = $prevLeadTagIds->intersect($leadTagIdsAfterChange);
            $addedTagIds = $leadTagIdsAfterChange->diff($existentTagIds)->values();
            foreach ($addedTagIds as $addedTagId) {
                $params = [$loginUserId, $lead->id, $addedTagId, $currentDateString];
                $this->doCustomDispatch(SaveLeadTagAddedJob::class, $params, null, $clientId);
            }
        }
    }


    public function leadUserUpdated(Lead $lead, array $oldUserArr, User $newUser)
    {
        $this->initializeLoginUser();
        $oldUserArr = collect($oldUserArr)->only(['id', 'name', 'email', 'username', 'last_name'])->toArray();
        $params = [$lead->id, $oldUserArr, $newUser->id, $this->getLoginUserId(), $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadUserUpdatedJob::class, $params, null, $lead->client_id);
    }


    public function multipleLeadUserUpdated(
        array $leadIds,
        array $oldUserIds,
        User $newUser,
        User $loginUser,
        int $delaySecs = 0
    ) {
        $this->initializeLoginUser();
        $params = [$leadIds, $oldUserIds, $newUser->id, $loginUser->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveMultipleLeadUserUpdatedJob::class, $params, $delaySecs, $newUser->client_id);
    }


    public function leadStatusUpdated(int $leadId, array $oldStatus, Status $newStatus)
    {
        $this->initializeLoginUser();
        $params = [$this->getLoginUserId(), $leadId, $oldStatus, $newStatus->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadStatusUpdatedJob::class, $params, null, $newStatus->client_id);
    }


    public function leadAcquisitionChannelUpdated(int $leadId, ?array $oldChannel, AcquisitionChannel $newChannel)
    {
        $this->initializeLoginUser();
        $params = [$this->getLoginUserId(), $leadId, $oldChannel, $newChannel->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadAcquisitionChannelUpdatedJob::class, $params, null, $newChannel->client_id);
    }


    public function leadUpdated(Lead $oldLead, array $newLeadAttrs)
    {
        if ($newLeadAttrs['quality'] ?? false) {
            $this->initializeLoginUser();
            $userId = $this->getLoginUserId();
            $oldQuality = $oldLead->quality;
            $newQuality = $newLeadAttrs['quality'];
            if ($newQuality != $oldQuality) {
                $params = [$userId, $oldLead->id, $oldQuality, $newQuality, $this->getCurrentDate()];
                $this->doCustomDispatch(SaveLeadQualityUpdatedJob::class, $params, null, $oldLead->client_id);
            }
        }
    }


    public function leadManuallyCreated(Lead $lead)
    {
        $this->initializeLoginUser();
        $params = [$this->getLoginUserId(), $lead->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadManuallyCreatedJob::class, $params, null, $lead->client_id);
    }


    // Botón normal wap (no wapi, no wap sender)
    public function whatsAppMessageSent(Lead $lead, string $phoneNumber, ?string $text = null)
    {
        $this->initializeLoginUser();
        $params = [$this->getLoginUserId(), $lead->id, $phoneNumber, $text, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveWhatsAppMessageSentJob::class, $params, null, $lead->client_id);
    }


    public function whatsAppSendingMessageSent(WhatsAppSendingMessage $wapSendingMsg)
    {
        $params = [$wapSendingMsg->id, $this->getCurrentDate()];
        $this->doCustomDispatch(
            SaveWhatsAppSendingMessageSentTimelineEventJob::class, $params, null, $wapSendingMsg->client_id
        );
    }


    public function whatsAppSendingMessagesScheduled(WhatsAppSending $wapSending)
    {
        foreach ($wapSending->whatsAppSendingMessages as $wapSendingMsg) {
            $params = [$wapSendingMsg->id, $this->getCurrentDate()];
            $this->doCustomDispatch(
                SaveWhatsAppSendingMessageScheduledTimelineEventJob::class, $params, null, $wapSendingMsg->client_id
            );
        }
    }


    public function phoneCallButtonClicked(Lead $lead, string $phoneNumber)
    {
        $this->initializeLoginUser();
        $params = [$this->getLoginUserId(), $lead->id, $phoneNumber, $this->getCurrentDate()];
        $this->doCustomDispatch(SavePhoneCallButtonClickedJob::class, $params, null, $lead->client_id);
    }


    public function leadCreated(Lead $lead)
    {
        $params = [$lead->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadCreatedJob::class, $params, null, $lead->client_id);
    }


    public function leadMassiveDeleted(User $user, Collection $deletedLeadIds, string $ip)
    {
        $params = [$user->id, $deletedLeadIds->toArray(), $ip, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadMassiveDeletedJob::class, $params, null, $user->client_id);
    }


    public function leadTaskCreated(Task $task)
    {
        $this->initializeLoginUser();
        $params = [$this->getLoginUserId(), $task->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadTaskAddedJob::class, $params, null, $task->client_id);
    }


    public function leadTaskUpdated(int $clientId, int $updatedTaskId, string $taskStatus)
    {
        if ($taskStatus == 'completed') {
            $this->initializeLoginUser();
            $params = [$this->getLoginUserId(), $updatedTaskId, $this->getCurrentDate()];
            $this->doCustomDispatch(SaveLeadTaskCompletedJob::class, $params, null, $clientId);
        }
    }


    public function leadTaskDeleted(int $clientId, int $taskId)
    {
        $this->initializeLoginUser();
        $params = [$this->getLoginUserId(), $taskId, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadTaskDeletedJob::class, $params, null, $clientId);
    }


    public function leadEmailScheduled(Email $email, int $delaySecs = 0)
    {
        $params = [$email->user_id, $email->id, $email->lead_id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadEmailScheduledJob::class, $params, $delaySecs, $email->client_id);
    }

    
    public function leadEmailCancelled(Email $email, User $user, int $delaySecs = 0)
    {
        $params = [$user->id, $email->id, $email->lead_id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadEmailCancelledJob::class, $params, $delaySecs, $email->client_id);
    }


    public function massiveEmailCancelled(User $user, array $cancelledExternalEmailIds)
    {
        $params = [$user->id, $cancelledExternalEmailIds];
        $this->doCustomDispatch(SaveMassiveEmailCancelledJob::class, $params, null, $user->client_id);
    }


    public function leadEmailSent(Email $email)
    {
        $params = [$email->user->id, $email->id, $email->lead->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadEmailSentJob::class, $params, null, $email->client_id);
    }


    public function leadEmailOpened(Email $email)
    {
        $params = [$email->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveLeadEmailOpenedJob::class, $params, null, $email->client_id);
    }


    public function userCreated(User $createdUser, User $loginUser)
    {
        $params = [$loginUser->id, $createdUser->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveUserCreatedJob::class, $params, null, $createdUser->client_id);
    }


    public function userEnabled(User $enabledUser, User $loginUser)
    {
        $params = [$loginUser->id, $enabledUser->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveUserEnabledJob::class, $params, null, $enabledUser->client_id);
    }


    public function userDisabled(User $disabledUser, User $loginUser)
    {
        $params = [$loginUser->id, $disabledUser->id, $this->getCurrentDate()];
        $this->doCustomDispatch(SaveUserDisabledJob::class, $params, null, $disabledUser->client_id);
    }


    public function setConnection(string $queueConnection)
    {
        $this->queueConnection = $queueConnection;
    }


    protected function getCurrentDate(): DateTime
    {
        return (new DateTime('now'));
        // return (new DateTime('now'))->format('Y-m-d\TH:i:sP');
    }

}
