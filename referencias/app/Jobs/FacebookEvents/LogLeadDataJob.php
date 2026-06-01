<?php

namespace App\Jobs\FacebookEvents;

use Throwable;
use Illuminate\Bus\Queueable;
use App\Models\ClientFacebookPage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\API\FacebookLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class LogLeadDataJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $fbFormDataArr;
    public $fbFormLeadDataArr;
    public $clientFacebookPage;


    // $fbFormDataArr sale de FacebookAdHelper::getFacebookFormDataById()
    public function __construct(
        ClientFacebookPage $clientFacebookPage,
        array $fbFormLeadDataArr,
        array $fbFormDataArr = []
    ) {
        $this->fbFormDataArr = $fbFormDataArr;
        $this->fbFormLeadDataArr = $fbFormLeadDataArr;
        $this->clientFacebookPage = $clientFacebookPage;
    }


    public function handle()
    {
        resolve(FacebookLogService::class)->saveLeadData(
            $this->clientFacebookPage, $this->fbFormLeadDataArr, $this->fbFormDataArr
        );
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}