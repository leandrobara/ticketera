<?php

namespace App\Services\API;

use DateTime;
use DateTimeZone;
use Illuminate\Support\Str;
use App\Helpers\DNSZoneHelper;
use App\Helpers\ClientyMailerAPIHelper;
use App\Services\Traits\GetClientFromRequest;


class AwsDkimService
{

    use GetClientFromRequest;


    public function __construct(
        private readonly DNSZoneHelper $dnsZoneHelper,
        private readonly ClientyMailerAPIHelper $clientyMailerAPIHelper,
    ) {
    }


    public function getDkimAndSpfCompleteInfo(string $domain): array
    {
        $awsDkimInfo = $this->getAwsSesDkimInfo($domain);
        $dnsZoneTxtRecord = $this->getTxtSpfRecordValueFromDnsZone($domain);
        $dnsZoneDkimRecords = $this->getCnameDkimRecordValuesFromDnsZone($awsDkimInfo['cnameRecords'] ?? []);
        $completeInfo = [
            'domain' => $domain,
            'awsDkimInfo' => $awsDkimInfo,
            'dnsZoneTxtRecord' => $dnsZoneTxtRecord,
            'dnsZoneDkimRecords' => $dnsZoneDkimRecords,
        ];
        return $completeInfo;
    }


    public function getAwsSesDkimInfo(string $domain): array
    {
        $dkimInfo = $this->clientyMailerAPIHelper->getAwsDkimInfo($domain);
        return $dkimInfo;
    }


    public function getCnameDkimRecordValuesFromDnsZone(array $awsDkimCnameRecords): array
    {
        $cnameRecords = [];
        foreach ($awsDkimCnameRecords as $awsDkimCnameRecord) {
            $awsRecordName = $awsDkimCnameRecord['name'];
            $awsRecordValue = $awsDkimCnameRecord['value'];
            $dnsZoneRecordValue = $this->dnsZoneHelper->getCNAMERecordValue($awsRecordName);
            $cnameRecords[$awsRecordName] = [
                'awsName' => $awsRecordName,
                'awsValue' => $awsRecordValue,
                'dnsZoneValue' => $dnsZoneRecordValue,
            ];
        }
        return $cnameRecords;
    }


    public function getTxtSpfRecordValueFromDnsZone(string $domain): array
    {
        $spfRecord = $this->dnsZoneHelper->findTxtSPFRecord($domain);
        $txtSpfRecord = $spfRecord['txt'] ?? null;
        $hasAmazonSpf = $txtSpfRecord && Str::contains($txtSpfRecord, 'include:amazonses.com');
        return ['spfRecord' => $txtSpfRecord, 'hasAmazonSpf' => $hasAmazonSpf];
    }


    public function ensureAwsDkimIntegrity(string $domain): array
    {
        $awsDkimResponse = $this->clientyMailerAPIHelper->ensureAwsDkimIntegrity($domain);
        return $awsDkimResponse;
    }

}
