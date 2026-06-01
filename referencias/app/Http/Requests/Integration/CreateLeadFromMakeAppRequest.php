<?php

namespace App\Http\Requests\Integration;

use App\Models\Lead;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Rules\StringOrNumeric;
use App\Models\LeadCustomField;
use App\Services\API\UserService;
use App\Services\API\LeadService;
use Illuminate\Support\Facades\Log;
use App\Services\API\StatusService;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\ClientSettingsService;
use App\Services\API\LeadCustomFieldService;
use App\Services\API\AcquisitionChannelService;
use App\DTO\Integration\CreateNewIntegrationMakeAppLeadDTO;


class CreateLeadFromMakeAppRequest extends APIBaseRequest
{

    private $acquisitionChannel;
    private $maxOtherFieldsCount = 10;
    private $maxCustomFieldsCount = 10;
    private $otherFieldsValidArr = [];
    private $customFieldsValidArr = [];

    
    protected function prepareForValidation()
    {
        // Largos máximos por campo del request, para evitar
        // que valores excedidos hagan fallar el INSERT. Se truncan acá.
        $fieldMaxLengths = [
            'name' => 80,
            'phone' => 40,
            'fbclid' => 255,
            'company' => 120,
            'lastName' => 80,
            'utm_source' => 150,
            'utm_medium' => 150,
            'utm_content' => 200,
            'utm_campaign' => 200,
            'utm_keywords' => 150,
            'acquisitionChannel' => 100,
        ];

        $truncated = [];
        foreach ($fieldMaxLengths as $field => $maxLength) {
            $value = $this->input($field);
            if (is_string($value) && mb_strlen($value) > $maxLength) {
                $truncated[$field] = mb_substr($value, 0, $maxLength);
            }
        }
        if (!empty($truncated)) {
            $this->merge($truncated);
        }
    }


    public function rules()
    {
        $nullableStringOrNumeric = new StringOrNumeric(['nullable' => true]);
        $landingRegex = 'regex:/[(http(s)?):\/\/(www\.)?a-zA-Z0-9@:%._\+~#=]'.
            '{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&\/\/=]*)/'
        ;
        return [
            'note' => ['sometimes', 'nullable', 'string'],
            'name' => ['sometimes', 'nullable', 'string'],
            'email' => ['sometimes', 'nullable', 'string'],
            'company' => ['sometimes', 'nullable', 'string'],
            'message' => ['sometimes', 'nullable', 'string'],
            'lastName' => ['sometimes', 'nullable', 'string'],
            'phone' => ['sometimes', $nullableStringOrNumeric],
            'otherFields' => ['sometimes', 'nullable', 'array'],
            'customFields' => ['sometimes', 'nullable', 'array'],
            'landingUrl' => ['sometimes', 'nullable', $landingRegex],
            'acquisitionChannel' => ['sometimes', 'nullable', 'string'],

            'fbclid' => ['sometimes', 'nullable', 'string'],
            'utm_source' => ['sometimes', 'nullable', 'string'],
            'utm_medium' => ['sometimes', 'nullable', 'string'],
            'utm_content' => ['sometimes', 'nullable', 'string'],
            'utm_campaign' => ['sometimes', 'nullable', 'string'],
            'utm_keywords' => ['sometimes', 'nullable', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                
                if ($this->leadIsEmpty()) {
                    $validator->errors()->add('lead', 'lead_main_data_is_empty');
                    return false;
                }

                $existentLead = $this->findExistentLead();
                if ($existentLead) {
                    $validator->errors()->add('lead', 'lead_already_exists');
                    return false;
                }

                $channelName = request()->input('acquisitionChannel');
                if ($channelName) {
                    $this->acquisitionChannel = resolve(AcquisitionChannelService::class)->findOneByClientAndName(
                        $client, $channelName
                    );
                }

                $otherFields = request()->input('otherFields');
                if ($otherFields) {
                    $otherFields = array_slice($otherFields, 0, $this->maxOtherFieldsCount);
                    foreach ($otherFields as $otherField) {
                        if (!isset($otherField['name']) || !isset($otherField['value'])) {
                            continue;
                        }
                        $this->otherFieldsValidArr[] = [
                            'name' => trim($otherField['name']),
                            'value' => trim($otherField['value']),
                        ];
                    }
                }

                $customFields = request()->input('customFields');
                if ($customFields) {
                    $customFields = array_slice($customFields, 0, $this->maxCustomFieldsCount);
                    $clientLeadCustomFields = resolve(LeadCustomFieldService::class)->findAllByClient($client);

                    foreach ($customFields as $customField) {
                        if (!isset($customField['name']) || !isset($customField['value'])) {
                            continue;
                        }
                        $customFieldName = trim(strtolower($customField['name']));
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
                                'value' => $customField['value'],
                                'leadCustomField' => $leadCustomField,
                            ];
                        }
                    }
                }
            }
        });
    }


    public function validatedDTO(): CreateNewIntegrationMakeAppLeadDTO
    {
        $validated = parent::validated();

        $client = request()->input('client');
        if (!$this->isValidEmail($validated['email'] ?? null)) {
            unset($validated['email']);
        }
        $validated['otherFields'] = $this->otherFieldsValidArr;
        $validated['customFields'] = $this->customFieldsValidArr;
        $validated['acquisitionChannel'] = $this->acquisitionChannel;
        $validated['phone'] = $this->sanitizePhoneNumber($validated['phone'] ?? null);
        
        $dto = CreateNewIntegrationMakeAppLeadDTO::buildFromRequestArray($validated);
        return $dto;
    }


    private function isValidEmail(?string $email): bool
    {
        if (!$email) {
            return false;
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }


    private function sanitizePhoneNumber(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }
        return filter_var($phone, FILTER_SANITIZE_NUMBER_INT);
    }


    private function leadIsEmpty(): bool
    {
        $name = request()->input('name');
        $email = request()->input('email');
        $phone = request()->input('phone');
        $lastName = request()->input('last_name');
        return (
            Str::of($name)->trim()->isEmpty() &&
            Str::of($email)->trim()->isEmpty() &&
            Str::of($phone)->trim()->isEmpty() &&
            Str::of($lastName)->trim()->isEmpty()
        );
    }


    private function findExistentLead(): ?Lead
    {
        $leadAttrs = [];
        $leadAttrs['method'] = 'form';
        $leadAttrs['company'] = request()->input('company') ?? null;
        $leadAttrs['message'] = request()->input('message') ?? null;
        $mainLeadContactAttrs['name'] = request()->input('name') ?? null;
        $mainLeadContactAttrs['email'] = request()->input('email') ?? null;
        $mainLeadContactAttrs['last_name'] = request()->input('lastName') ?? null;
        $mainLeadContactAttrs['phone'] = (request()->input('phone'))
            ? $this->sanitizePhoneNumber(request()->input('phone'))
            : null
        ;
        
        $client = request()->input('client');
        $hash = Lead::buildHash($leadAttrs, $mainLeadContactAttrs);
        $lead = resolve(LeadService::class)->findOneByClientAndHash($client, $hash);
        return $lead;
    }

}
