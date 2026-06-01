<?php

namespace App\Http\Requests\Integration;

use App\Models\LeadCustomField;
use App\Services\API\LeadService;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\LeadCustomFieldService;
use App\Services\API\LeadContactPhoneService;
use App\Services\API\AcquisitionChannelService;
use App\DTO\Integration\UpdateIntegrationLeadDTO;


class UpdateLeadRequest extends APIBaseRequest
{

    private $maxCustomFieldsCount = 10;
    private $customFieldsValidArr = [];
    private $methodIntegration = 'form';
    private $acquisitionChannelId = null;


    public function rules()
    {
        return [
            'name' => ['sometimes', 'string'],
            'lastName' => ['sometimes', 'nullable', 'string'],
            'email' => ['sometimes', 'nullable', 'email'],
            'email2' => ['sometimes', 'nullable', 'email'],
            'phone' => ['sometimes', 'nullable', 'string'],
            'phone2' => ['sometimes', 'nullable', 'string'],
            'company' => ['sometimes', 'nullable', 'string'],
            'customFields' => ['sometimes', 'nullable', 'array'],
            'quality' => ['sometimes', 'integer', 'between:0,3'],
            'acquisitionChannel' => ['sometimes', 'nullable', 'array'],
            'acquisitionChannel.name' => ['required_with:acquisitionChannel', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');

                $lead = request()->lead;
                $leadContactEmails = $lead->mainLeadContact->leadContactEmails;
                $leadContactPhones = $lead->mainLeadContact->leadContactPhones;

                $firstPhone = request()->input('phone');
                $firstEmail = request()->input('email');
                $secondPhone = request()->input('phone2');
                $secondEmail = request()->input('email2');
                $customFields = request()->input('customFields');
                $channel = request()->input('acquisitionChannel');
                $channelName = $channel ? ($channel['name'] ?? null) : null;

                if ($firstEmail && $secondEmail && $firstEmail == $secondEmail) {
                    $validator->errors()->add('email', 'emails_can_not_be_the_same');
                    return false;
                }
                if ($firstPhone && $secondPhone && $firstPhone == $secondPhone) {
                    $validator->errors()->add('email', 'phones_can_not_be_the_same');
                    return false;
                }
 
                if ($channelName) {
                    $existentChannel = resolve(AcquisitionChannelService::class)->findOneByClientAndName(
                        $client, $channelName
                    );
                    if (!$existentChannel) {
                        $validator->errors()->add('lead_acquisition_channel', 'acquisition_channel_does_not_exist');
                        return false;
                    }
                    $this->acquisitionChannelId = $existentChannel->id;
                }

                if ($customFields) {
                    $customFields = array_slice($customFields, 0, 5);
                    $clientLeadCustomFields = resolve(LeadCustomFieldService::class)->findAllByClient();
                    foreach ($customFields as $customField) {
                        $customFieldVal = $customField['value'];
                        $customFieldName = trim($customField['name']);
                        $leadCustomField = $clientLeadCustomFields
                            ->map(function ($leadCustomField) {
                                $leadCustomField->name = trim(strtolower($leadCustomField->name));
                                return $leadCustomField;
                            })
                            ->where('name', trim(strtolower($customFieldName)))
                            ->where('client_id', $client->id)
                            ->first()
                        ;

                        if (!$leadCustomField) {
                            $validator->errors()->add('lead_custom_field', 'lead_custom_field_does_not_exist');
                            return false;
                        }
                        
                        $this->customFieldsValidArr[] = [
                            'value' => $customFieldVal, 'leadCustomField' => $leadCustomField,
                        ];
                    }
                }
            }
        });
    }


    public function validatedDTO(): UpdateIntegrationLeadDTO
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
        $dto = UpdateIntegrationLeadDTO::buildFromRequestArray($validated);
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
