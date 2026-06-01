<?php

namespace App\DTO\Import;

use Illuminate\Support\Str;


class LeadsClientDTO
{

    public $id = null;
    public $name = null;
    public $type = null;
    public $emails = null;
    public $enabled = null;
    public $manager = null;
    public $landings = null;
    public $createdAt = null;
    public $CRMEnabled = null;
    public $CRMVersion = null;
    public $googleAdsId = null;
    public $countryCode = null;
    public $businessArea = null;
    public $emailFromName = null;
    public $monthlyBudget = null;
    public $CRMTestVersion = null;
    public $contractType = 'clienty';
    public $businessAreaChild = null;
    public $CRMTestVersionDays = null;
    public $CRMTestVersionCreatedAt = null;
    public $CRMMonthlyEmailSendingQuota = null;
    public $CRMWhatsAppTemplatesEnabled = null;


    public static function buildFromLeadsClientData(array $leadsClientData): LeadsClientDTO
    {
        $dto = new LeadsClientDTO();
        $dto->name = $leadsClientData['name'];
        $dto->type = $leadsClientData['type'];
        $dto->id = (int) $leadsClientData['id'];
        $dto->landings = $leadsClientData['landings'];
        $dto->createdAt = $leadsClientData['createdAt'];
        $dto->CRMVersion = $leadsClientData['crm_version'];
        $dto->enabled = (bool) $leadsClientData['enabled'];
        $dto->countryCode = $leadsClientData['country_code'];
        $dto->googleAdsId = $leadsClientData['google_ads_id'];
        $dto->monthlyBudget = $leadsClientData['monthly_budget'];
        $dto->emailFromName = $leadsClientData['email_from_name'];
        $dto->CRMEnabled = (bool) $leadsClientData['enabled_crm'];
        $dto->CRMTestVersion = $leadsClientData['crm_test_version'];
        $dto->contractType = $leadsClientData['contract_type'] ?? 'clienty';
        $dto->CRMTestVersionDays = $leadsClientData['crm_test_version_days'];
        $dto->CRMTestVersionCreatedAt = $leadsClientData['crm_test_version_created_at'];
        $dto->CRMWhatsAppTemplatesEnabled = (bool) $leadsClientData['crm_whatsapp_templates_enabled'];
        $dto->CRMMonthlyEmailSendingQuota = (int) $leadsClientData['crm_monthly_email_sending_quota'];
        $dto->businessArea = $leadsClientData['business_area'];
        $dto->businessAreaChild = $leadsClientData['business_area_child'];

        $emails = explode(';', $leadsClientData['email']);
        $emails = array_map(function ($e) {
            return strtolower(trim($e));
        }, $emails);
        $emails = array_filter($emails, function ($e) {
            return $e;
        });
        $dto->emails = $emails;

        $dto->manager = [
            'name' => $leadsClientData['manager_name'],
            'email' => $leadsClientData['manager_email'],
            'phone' => $leadsClientData['manager_phone'],
        ];
        return $dto;
    }

}
