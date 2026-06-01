<?php

namespace App\DTO\Import;

use Exception;
use App\Models\Lead;


class ImportedLeadDTO
{

    const STATUS_EXISTENT = 'existent';
    const STATUS_IMPORTED = 'imported';
    const STATUS_NON_ENABLED_CLIENT = 'nonenabled_client';
    const STATUS_NONEXISTENT_CLIENT = 'nonexistent_client';
    const STATUS_NONEXISTENT_CONTACT_INFO = 'nonexistent_contact_info';
    const STATUS_NON_ENABLED_TO_RECEIVE_LEADS_CLIENT = 'nonenabled_to_receive_leads_client';

    public $lead;
    public $leadsLeadDTO;
    public $importStatus;


    public function __construct(?Lead $lead, ImportLeadDTOInterface $leadsLeadDTO, string $importStatus)
    {
        $allowedStatus = [
            self::STATUS_EXISTENT,
            self::STATUS_IMPORTED,
            self::STATUS_NONEXISTENT_CLIENT,
            self::STATUS_NON_ENABLED_CLIENT,
            self::STATUS_NONEXISTENT_CONTACT_INFO,
            self::STATUS_NON_ENABLED_TO_RECEIVE_LEADS_CLIENT,
        ];
        if (!in_array($importStatus, $allowedStatus)) {
            throw new Exception('ImportedLeadDTO invalid import status');
        }
        $this->lead = $lead;
        $this->leadsLeadDTO = $leadsLeadDTO;
        $this->importStatus = $importStatus;
    }


    public function __toString(): string
    {
        $leadId = $this->lead ? $this->lead->id : 'NO_LEAD';
        $clientName = $this->lead ? $this->lead->client->subdomain : null;
        $importStatus = strtoupper($this->importStatus);

        $str = "Import Status: <b>{$importStatus}</b>";
        if ($clientName) {
            $str .= " - Client: {$clientName}";
        }
        $str .= " - Leads Lead info: {$this->leadsLeadDTO->getContactInfoString()}";
        $str .= " - Imported Lead ID: {$leadId}";
        return $str;
    }

}
