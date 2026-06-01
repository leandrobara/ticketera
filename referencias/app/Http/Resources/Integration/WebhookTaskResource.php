<?php

namespace App\Http\Resources\Integration;

use App\Models\Task;
use Illuminate\Support\Collection;
use App\Http\Resources\Integration\WebhookLeadResource;


class WebhookTaskResource
{

    private $task = null;
    private $triggerCode = null;


    public function __construct(Task $task, string $triggerCode)
    {
        $this->task = $task;
        $this->triggerCode = $triggerCode;
    }


    public function toArray()
    {
        $leadArr = [];
        if ($this->task->lead) {
            $leadArr = (new LeadResource($this->task->lead))->toArray();
        }

        $taskUser = $this->loadTaskUser();
        $createdDate = $this->task->created_at->format('Y-m-d\TH:i:sO');
        $limitDate = $this->task->limit_date ? $this->task->limit_date->format('Y-m-d\TH:i:sO') : null;

        $response = [
            'triggerCode' => $this->triggerCode,
            'task' => [
                'id' => $this->task->id,
                'title' => $this->task->title,
                'status' => $this->task->status,
                'description' => $this->task->description,
                'limitDate' => $limitDate,
                'createdDate' => $createdDate,
                'isImportant' => $this->task->is_important,
                'user' => $taskUser,
                'lead' => $leadArr,
            ],
        ];
        return $response;
    }


    private function loadTaskUser(): array
    {
        if (!$this->task->relationLoaded('user')) {
            $this->task->load('user');
        }
        $user = $this->task->user->toArray();
        return ['name' => $user['name'], 'last_name' => $user['last_name'], 'email' => $user['email']];
    }

}
