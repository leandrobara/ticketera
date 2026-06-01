<?php

namespace App\Jobs\TimelineEvents;

use Throwable;
use App\Models\Lead;
use App\Models\User;
use App\Models\Email;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use App\Services\API\EventsLogService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;


class SaveMassiveEmailCancelledJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    public $timeout = 300;
    
    public $userId;
    public $cancelledExternalEmailIds;

    
    public function __construct(int $userId, array $cancelledExternalEmailIds)
    {
        $this->userId = $userId;
        $this->cancelledExternalEmailIds = $cancelledExternalEmailIds;
    }


    public function handle()
    {
        $service = resolve(TimelineEventsDispatcherService::class);
        $user = resolve(UserService::class)->findOrFail($this->userId);
        $queryBuilder = Email::whereIn('external_id', $this->cancelledExternalEmailIds)
            ->where('client_id', $user->client_id)
        ;
        $queryBuilder->chunk(200, function ($emails) use ($user, $service) {
            foreach ($emails as $email) {
                $service->leadEmailCancelled($email, $user);
            }
        });
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
