<?php

namespace App\Http\Requests\Integration;

use App\Models\LeadCustomField;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\LeadCustomFieldService;
use App\Services\API\AcquisitionChannelService;
use App\DTO\Integration\UpdateIntegrationZapierAppLeadDTO;


class UpdateLeadFromZapierAppRequest extends APIBaseRequest
{

    private $maxCustomFieldsCount = 10;
    private $customFieldsValidArr = [];
    private $acquisitionChannelId = null;


    public function rules()
    {
        return [
            'lead' => ['required', 'array'],
            'lead.name' => ['sometimes', 'string'],
            'lead.email' => ['sometimes', 'nullable', 'email'],
            'lead.email2' => ['sometimes', 'nullable', 'email'],
            'lead.phone' => ['sometimes', 'nullable', 'string'],
            'lead.phone2' => ['sometimes', 'nullable', 'string'],
            'lead.acquisitionChannel' => ['sometimes', 'string'],
            'lead.company' => ['sometimes', 'nullable', 'string'],
            'lead.lastName' => ['sometimes', 'nullable', 'string'],
            'lead.customFields' => ['sometimes', 'nullable', 'array'],
            'lead.quality' => ['sometimes', 'integer', 'between:0,3'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $customFields = request()->input('lead.customFields');
                $channelName = request()->input('lead.acquisitionChannel');
                
                if ($channelName) {
                    $existentChannel = resolve(AcquisitionChannelService::class)->findOneByClientAndName(
                        $client, $channelName
                    );
                    if ($existentChannel) {
                        $this->acquisitionChannelId = $existentChannel->id;
                    }
                }

                if ($customFields) {
                    $customFields = array_slice($customFields, 0, $this->maxCustomFieldsCount);
                    $clientLeadCustomFields = resolve(LeadCustomFieldService::class)->findAllByClient($client);
                    foreach ($customFields as $customFieldName => $customFieldValue) {
                        $customFieldName = trim(strtolower($customFieldName));
                        if (!$customFieldName) {
                            continue;
                        }
                        $leadCustomField = $clientLeadCustomFields
                            ->map(function (LeadCustomField $leadCustomField) {
                                $leadCustomField->name = trim(strtolower($leadCustomField->name));
                                return $leadCustomField;
                            })
                            ->where('name', $customFieldName)
                            ->where('client_id', $client->id)
                            ->first()
                        ;
                        if ($leadCustomField) {
                            $this->customFieldsValidArr[] = [
                                'leadCustomField' => $leadCustomField, 'value' => $customFieldValue
                            ];
                        }
                    }
                }
            }
        });
    }


    public function validatedDTO(): UpdateIntegrationZapierAppLeadDTO
    {
        $validated = parent::validated();
        
        if ($this->customFieldsValidArr) {
            $validated['lead']['customFields'] = $this->customFieldsValidArr;
        }
        if ($this->acquisitionChannelId) {
            $validated['lead']['acquisitionChannelId'] = $this->acquisitionChannelId;
        }
        if (isset($validated['lead']['email']) && !$this->isValidEmail($validated['lead']['email'])) {
            unset($validated['lead']['email']);
        }
        if (isset($validated['lead']['email2']) && !$this->isValidEmail($validated['lead']['email2'])) {
            unset($validated['lead']['email2']);
        }
        if (isset($validated['phone'])) {
            $validated['phone'] = $this->sanitizePhoneNumber($validated['lead']['phone']);
        }
        if (isset($validated['lead']['phone2'])) {
            $validated['lead']['phone2'] = $this->sanitizePhoneNumber($validated['lead']['phone2']);
        }
        $dto = UpdateIntegrationZapierAppLeadDTO::buildFromRequestArray($validated);
        return $dto;
    }


    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }


    private function sanitizePhoneNumber(string $phone): ?string
    {
        return filter_var($phone, FILTER_SANITIZE_NUMBER_INT);
    }

}
