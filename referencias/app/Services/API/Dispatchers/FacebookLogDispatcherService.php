<?php

namespace App\Services\API\Dispatchers;

use App\Models\ClientFacebookPage;
use App\Services\Traits\CustomDispatch;
use App\Jobs\FacebookEvents\LogLeadDataJob;
use App\Jobs\FacebookEvents\LogLeadGenDataJob;
use Illuminate\Foundation\Bus\PendingDispatch;


class FacebookLogDispatcherService
{
    
    use CustomDispatch;

    private $eventQueueName;
    private $queueConnection;


    public function __construct(string $eventQueueName, string $queueConnection)
    {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
    }


    public function logLeadGenDataReceived(array $data)
    {
        $this->doCustomDispatch(LogLeadGenDataJob::class, [$data]);
    }


    // $fbFormDataArr sale de FacebookAdHelper::getFacebookFormDataById()
    public function logLeadDataReceived(
        ClientFacebookPage $clientFacebookPage,
        array $fbLeadDataArr,
        array $fbFormDataArr = []
    ) {
        $this->doCustomDispatch(LogLeadDataJob::class, [$clientFacebookPage, $fbLeadDataArr, $fbFormDataArr]);
    }

}
