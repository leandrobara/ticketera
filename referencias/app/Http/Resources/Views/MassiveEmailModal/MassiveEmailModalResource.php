<?php

namespace App\Http\Resources\Views\MassiveEmailModal;

use App\Services\Traits\GetClientFromRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class MassiveEmailModalResource extends JsonResource
{

    use VisibleFieldsFilter;
    use GetClientFromRequest;


    // $resource -> App\DTO\MassiveEmailModalDTO;
    public function toArray($request)
    {
        $client = $this->getClient();

        $leadContactEmails = $this->resource->leadContactEmails->map(function ($leadContactEmail) {
            return [
                'id' => $leadContactEmail->id,
                'email' => $leadContactEmail->email,
                'lead_id' => $leadContactEmail->lead_id,
                'bounced' => $leadContactEmail->bounced,
                'is_valid' => $leadContactEmail->is_valid,
                'complained' => $leadContactEmail->complained,
                'validations' => $leadContactEmail->validations,
                'unsubscribed' => $leadContactEmail->unsubscribed,
                'lead_contact_id' => $leadContactEmail->lead_contact_id,
            ];
        });

        $response = [
            'leadContactEmails' => $leadContactEmails,
            'emailSendingBlocked' => $this->resource->emailSendingBlocked ?? false,

            'dailyQuota' => $this->resource->emailQuotaInfoDTO->dailyQuota,
            'dailyUsedQuota' => $this->resource->emailQuotaInfoDTO->dailyUsedQuota,
            'availableDailyQuota' => $this->resource->emailQuotaInfoDTO->availableDailyQuota,
            
            'monthlyQuota' => $this->resource->emailQuotaInfoDTO->monthlyQuota,
            'monthlyUsedQuota' => $this->resource->emailQuotaInfoDTO->monthlyUsedQuota,
            'availableMonthlyQuota' => $this->resource->emailQuotaInfoDTO->availableMonthlyQuota,
        ];
        return $response;
    }

}
