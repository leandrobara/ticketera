<?php

namespace App\Http\Requests\Integration;

use App\Models\LeadCustomField;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\LeadCustomFieldService;
use App\Services\API\AcquisitionChannelService;
use App\DTO\Integration\UpdateIntegrationMakeAppLeadDTO;


class UpdateLeadFromMakeAppRequest extends APIBaseRequest
{

    private $maxCustomFieldsCount = 10;
    private $customFieldsValidArr = [];
    private $acquisitionChannelId = null;


    public function rules()
    {
        return [
            'name' => ['sometimes', 'string'],
            'email' => ['sometimes', 'nullable', 'email'],
            'email2' => ['sometimes', 'nullable', 'email'],
            'phone' => ['sometimes', 'nullable', 'string'],
            'phone2' => ['sometimes', 'nullable', 'string'],
            'acquisitionChannel' => ['sometimes', 'string'],
            'company' => ['sometimes', 'nullable', 'string'],
            'lastName' => ['sometimes', 'nullable', 'string'],
            'customFields' => ['sometimes', 'nullable', 'array'],
            'quality' => ['sometimes', 'integer', 'between:0,3'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $customFields = request()->input('customFields');
                $channelName = request()->input('acquisitionChannel');

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

                    foreach ($customFields as $customField) {
                        $customFieldName = $customField['name'] ?? null;
                        if (!$customFieldName) {
                            continue;
                        }
                        $customFieldValue = $customField['value'] ?? null;
                        $customFieldName = trim(strtolower($customFieldName));
                        $leadCustomField = $clientLeadCustomFields
                            ->map(function (LeadCustomField $leadCustomField) use ($customFieldName) {
                                $leadCustomField->name = trim(strtolower($leadCustomField->name));
                                return $leadCustomField;
                            })
                            ->where('name', $customFieldName)
                            ->where('client_id', $client->id)
                            ->first()
                        ;
                        if ($leadCustomField) {
                            $this->customFieldsValidArr[] = [
                                'value' => $customFieldValue, 'leadCustomField' => $leadCustomField,
                            ];
                        }
                    }
                }
            }
        });
    }


    public function validatedDTO(): UpdateIntegrationMakeAppLeadDTO
    {
        $validated = parent::validated();

        if ($this->customFieldsValidArr) {
            $validated['customFields'] = $this->customFieldsValidArr;
        }
        if ($this->acquisitionChannelId) {
            $validated['acquisitionChannelId'] = $this->acquisitionChannelId;
        }
        if (isset($validated['email']) && !$this->isValidEmail($validated['email'])) {
            unset($validated['email']);
        }
        if (isset($validated['email2']) && !$this->isValidEmail($validated['email2'])) {
            unset($validated['email2']);
        }
        if (isset($validated['phone'])) {
            $validated['phone'] = $this->sanitizePhoneNumber($validated['phone']);
        }
        if (isset($validated['phone2'])) {
            $validated['phone2'] = $this->sanitizePhoneNumber($validated['phone2']);
        }
        $dto = UpdateIntegrationMakeAppLeadDTO::buildFromRequestArray($validated);
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
