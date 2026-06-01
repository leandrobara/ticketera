<?php

namespace App\Jobs\UserEvents;

use Throwable;
use Exception;
use App\Models\User;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\UserEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;


class DisableUserWAPIJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;
    

    public function __construct(public readonly int $userId)
    {
    }


    public function handle()
    {
        $userService = resolve(UserService::class);
        $user = $userService->findOrFail($this->userId);
        if ($user->wapi_session_phone_number) {
            $userService->update($user, ['wapi_is_synced' => false]);
        }
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
        $this->getErrorLog()->error(PHP_EOL . PHP_EOL);
    }

}
