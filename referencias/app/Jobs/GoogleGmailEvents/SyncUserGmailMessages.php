<?php

namespace App\Jobs\GoogleGmailEvents;

use Throwable;
use App\Models\User;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\API\GoogleGmailAPIService;
use App\Services\API\GoogleAPIUserContactService;
use App\Jobs\GoogleContactsEvents\Traits\InjectLog;


class SyncUserGmailMessages implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $backoff = 90;
    
    public $userId;
    

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }


    public function handle()
    {
        $service = resolve(GoogleGmailAPIService::class);
        $user = resolve(UserService::class)->findOrFail($this->userId);
        $isAPIEnabled = $service->isAPIEnabled($user->googleGmailAPIUserToken);
        if (!$isAPIEnabled) {
            return null;
        }

        $gmailMessagesDtos = $service->listMessages($user);
        
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
