<?php

namespace App\Services\API;

use DateTime;
use App\Models\ClientFacebookPage;
use App\Models\MongoDB\FacebookAPILog;


class FacebookLogService
{

    public function __construct()
    {
    }


    public function saveLeadGenData(array $logData): bool
    {
        $date = new DateTime('now');
        $facebookLogData = [
            'log' => $logData,
            'createdAt' => $date,
            'system' => 'clienty_crm',
            'event' => 'leadgen_data_received',
            'createdAtTs' => $date->getTimestamp(),
        ];
        $facebookLog = new FacebookAPILog($facebookLogData);
        $facebookLog->hash = FacebookAPILog::buildHash($facebookLog->system, $facebookLog->event, $facebookLog->log);
        $facebookLog->save();
        return true;
        // $this->eventLogAPIHelper->createFacebookLog($leadGenData);
    }


    // $fbFormDataArr sale de FacebookAdHelper::getFacebookFormDataById()
    public function saveLeadData(
        ClientFacebookPage $clientFacebookPage,
        array $fbFormLeadDataArr,
        array $fbFormDataArr = []
    ) {
        $date = new DateTime('now');
        $logDataArr = $fbFormLeadDataArr;
        $logDataArr['fbFormDataArr'] = $fbFormDataArr;
        $logDataArr['clientFacebookPage'] = $clientFacebookPage->toArray();

        $facebookLogData = [
            'log' => $logDataArr,
            'createdAt' => $date,
            'system' => 'clienty_crm',
            'event' => 'lead_data_received',
            'createdAtTs' => $date->getTimestamp(),
        ];
        $facebookLog = new FacebookAPILog($facebookLogData);
        $facebookLog->hash = FacebookAPILog::buildHash($facebookLog->system, $facebookLog->event, $facebookLog->log);
        $facebookLog->save();
        return true;
        // return $this->eventLogAPIHelper->createFacebookLog($leadData);
    }


    public function findOneByFBLeadId(string $fbLeadId): ?FacebookAPILog
    {
        $fbLog = FacebookAPILog::where('log.id', $fbLeadId)->where('event', 'lead_data_received')->first();
        return $fbLog;
    }

}

