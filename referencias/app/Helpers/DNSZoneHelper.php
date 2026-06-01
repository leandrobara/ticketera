<?php

namespace App\Helpers;

use DateTime;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;


class DNSZoneHelper
{


    public function listTXTRecords(string $url): Collection
    {
        $dnsRecords = dns_get_record($url, DNS_TXT);
        return collect($dnsRecords);
    }


    public function findTxtSPFRecord(string $domain): ?array
    {
        $textRecords = $this->listTXTRecords($domain);

        return $textRecords->first(function ($item) {
            return isset($item['txt']) && Str::startsWith($item['txt'], 'v=spf1');
        });

        return $spfRecord;
    }


    public function getCNAMERecordValue(string $cnameRecordName): ?string
    {
        try {
            $dnsRecords = dns_get_record($cnameRecordName, DNS_CNAME);
            
            if (empty($dnsRecords)) {
                return null;
            }
            
            // Tomamos el primer registro CNAME encontrado
            $record = $dnsRecords[0];
            
            // El valor del CNAME está en el campo 'target'
            return $record['target'] ?? null;
        } catch (Exception $e) {
            // En caso de error en la consulta DNS, retornamos null
            return null;
        }
    }

}
