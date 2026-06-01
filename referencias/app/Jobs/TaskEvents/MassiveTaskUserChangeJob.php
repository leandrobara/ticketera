<?php

namespace App\Jobs\TaskEvents;

use Throwable;
use App\Models\User;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use App\Services\API\TaskService;
use Illuminate\Support\Collection;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\LeadEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;


class MassiveTaskUserChangeJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    protected $taskIds;
    protected $newUserId;
    

    public function __construct(array $taskIds, int $newUserId)
    {
        $this->taskIds = $taskIds;
        $this->newUserId = $newUserId;
    }


    public function handle()
    {
        resolve(TaskService::class)->updateMassiveTasks(collect($this->taskIds), ['user_id' => $this->newUserId]);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
