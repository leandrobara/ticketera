<?php

namespace App\Services\API;

use Exception;
use Throwable;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\DTO\NewTaskDTO;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\Traits\GetUserFromRequest;
use App\Services\Traits\GetClientFromRequest;
use App\Services\API\TaskNotificationEmailService;
use App\Services\API\Dispatchers\BrowserEventsDispatcher;
use App\Services\API\TaskNotificationWhatsAppMessageService;
use App\Services\API\Dispatchers\TaskEventsDispatcherService;
use App\Services\API\Dispatchers\ClientEventsDispatcherService;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;
use App\Services\API\Dispatchers\IntegrationAPIEventsDispatcherService;


class TaskService
{

    use GetClientFromRequest, GetUserFromRequest;


    public function __construct(
        private readonly Repository $taskRepository,
        private readonly BrowserEventsDispatcher $browserEventsDispatcher,
        private readonly TaskEventsDispatcherService $taskEventsDispatcherService,
        private readonly TaskNotificationEmailService $taskNotificationEmailService,
        private readonly ClientEventsDispatcherService $clientEventsDispatcherService,
        private readonly TimelineEventsDispatcherService $timelineEventsDispatcherService,
        private readonly IntegrationAPIEventsDispatcherService $integrationAPIEventsDispatcherService,
        private readonly TaskNotificationWhatsAppMessageService $taskNotificationWhatsAppMessageService,
    ) {
    }


    public function findAll(): Collection
    {
        return $this->taskRepository->findAllByClient($this->getClient());
    }


    public function findByClientAndIds(Client $client, array $taskIds): Collection
    {
        return $this->taskRepository->findByClientAndIds($client, $taskIds);
    }


    public function create(array $taskAttrs, ?User $loggedUser = null): Task
    {
        if (!($taskAttrs['client_id'] ?? null)) {
            $taskAttrs['client_id'] = $this->getClient()->id;
        }
        if (!($taskAttrs['user_id'] ?? null)) {
            $taskAttrs['user_id'] = $this->getUser()->id;
        }

        $loggedUser = $loggedUser ?? $this->getRequestUserOrNull();
        $isTaskAssignedToLoggedUser = $loggedUser && ($loggedUser->id == $taskAttrs['user_id']);

        $task = $this->taskRepository->create($taskAttrs);
            
        $this->dispatchSendNewTaskDataToWebhookJobIfEnabled($task);
        $this->timelineEventsDispatcherService->leadTaskCreated($task);

        if ($loggedUser && !$isTaskAssignedToLoggedUser) {
            $this->taskNotificationEmailService->createNewDefault($task);
            $this->taskNotificationWhatsAppMessageService->createNewDefault($task);

            $this->taskEventsDispatcherService->dispatchSendNewTaskEmailJob(
                task: $task, assignerUser: $loggedUser, delaySecs: 15
            );
            $this->taskEventsDispatcherService->dispatchSendNewTaskWhatsAppMessageJob(
                task: $task, assignerUser: $loggedUser, delaySecs: 15
            );
            $this->browserEventsDispatcher->notifyNewTask(task: $task, assignerUser: $loggedUser);
        }
        return $task;
    }


    public function update(
        Task $task,
        array $taskAttrs,
        ?User $loggedUser = null,
    ): Task {
        $oldTaskUserId = $task->user_id;
        $newTaskUserId = $taskAttrs['user_id'] ?? null;
        $updatedTask = $this->taskRepository->update($task, $taskAttrs);

        $userHasChanged = $newTaskUserId && ($newTaskUserId != $oldTaskUserId);

        $loggedUser = $loggedUser ?? $this->getRequestUserOrNull();

        $isTaskAssignedToLoggedUser = $loggedUser && ($loggedUser->id == $newTaskUserId);
        if ($userHasChanged && $loggedUser && !$isTaskAssignedToLoggedUser) {
            $this->taskNotificationEmailService->createTaskUserChangeDefault($updatedTask);
            $this->taskNotificationWhatsAppMessageService->createTaskUserChangeDefault($updatedTask);

            $this->taskEventsDispatcherService->dispatchSendTaskUserChangeEmailJob(
                task: $task, oldTaskUserId: $oldTaskUserId, assignerUser: $loggedUser
            );
            $this->taskEventsDispatcherService->dispatchSendTaskUserChangeWhatsAppMessageJob(
                task: $task, oldTaskUserId: $oldTaskUserId, assignerUser: $loggedUser
            );
            $this->browserEventsDispatcher->notifyTaskUserChange(task: $task, assignerUser: $loggedUser);
        }

        $this->timelineEventsDispatcherService->setLoginUser($loggedUser)->leadTaskUpdated(
            $task->client_id, $updatedTask->id, $updatedTask->status
        );

        return $updatedTask;
    }


    public function createMassive(Collection $leads, array $taskAttrs): Collection
    {
        $tasks = [];
        $taskAttrs['client_id'] = $taskAttrs['client_id'] ?? $this->getClient()->id;
        try {
            DB::beginTransaction();
            foreach ($leads as $lead) {
                $tasks[] = $this->create($taskAttrs + ['lead_id' => $lead->id]);
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        foreach ($tasks as $task) {
            $this->dispatchSendNewTaskDataToWebhookJobIfEnabled($task);
            $this->timelineEventsDispatcherService->leadTaskCreated($task);
        }

        return collect($tasks);
    }


    public function delete(Task $task): Task
    {
        $deleted = $this->taskRepository->delete($task);
        $this->timelineEventsDispatcherService->leadTaskDeleted($task->client_id, $task->id);
        return $deleted;
    }


    public function updateMassiveTasks(Collection $taskIds, array $attrs, ?int $clientId = null): Collection
    {
        $this->taskRepository->updateMassive($taskIds, $attrs);
        
        $markedAsCompleted = ($attrs['status'] ?? null) == 'completed';
        if ($markedAsCompleted) {
            $clientId = $clientId ?? $this->getClient()->id;
            foreach ($taskIds as $taskId) {
                $this->timelineEventsDispatcherService->leadTaskUpdated($clientId, $taskId, 'completed');
            }
        }
        return $taskIds;
    }


    public function deleteMassiveTasks(Collection $taskIds): Collection
    {
        $clientId = $this->getClient()->id;
        $this->taskRepository->deleteMassive($taskIds);

        foreach ($taskIds as $taskId) {
            $this->timelineEventsDispatcherService->leadTaskDeleted($clientId, $taskId);
        }
        return $taskIds;
    }


    public function setMassiveTasksUser(Collection $taskIds, User $newUser): bool
    {
        $loginUser = $this->getUser();
        $taskIdsChunks = $taskIds->chunk(200);
        foreach ($taskIdsChunks as $taskIdsChunk) {
            $this->taskEventsDispatcherService->dispatchMassiveTaskUserChangeJob($taskIdsChunk, $newUser);
        }

        $taskNotifEmail = $this->taskNotificationEmailService->createMassiveTaskUserChange($loginUser, $taskIds);
        $this->taskEventsDispatcherService->dispatchSendMassiveTaskUserChangeEmailJob($taskNotifEmail, 60); // delay 60
        $this->browserEventsDispatcher->notifyMassiveTaskUserChange($taskIds, $loginUser->id, $newUser->id);

        return true;
    }


    protected function dispatchSendNewTaskDataToWebhookJobIfEnabled(Task $task): void
    {
        $clientSettings = $task->client->clientSettings;
        if (!$clientSettings->enable_integration_api) {
            return;
        }
        $integrationAPIDispatcher = $this->integrationAPIEventsDispatcherService;

        $webhookUrl = $clientSettings->task_create_trigger_webhook;
        if ($webhookUrl) {
            $integrationAPIDispatcher->dispatchSendNewTaskDataToWebhookJob($task, $webhookUrl);
        }

        $zapierWebhookUrl = $clientSettings->task_create_trigger_zapier_webhook;
        if ($zapierWebhookUrl) {
            $integrationAPIDispatcher->dispatchSendNewTaskDataToWebhookJob($task, $zapierWebhookUrl);
        }

        $makeWebhookUrl = $clientSettings->task_create_trigger_make_webhook;
        if ($makeWebhookUrl) {
            $integrationAPIDispatcher->dispatchSendNewTaskDataToWebhookJob($task, $makeWebhookUrl);
        }
    }

}
