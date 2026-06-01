<?php

namespace App\Http\Requests\Integration;

use App\Models\Lead;
use App\Models\Client;
use Illuminate\Support\Str;
use App\Rules\StringOrNumeric;
use App\Models\LeadCustomField;
use App\Services\API\UserService;
use App\Services\API\LeadService;
use App\Services\API\StatusService;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\ClientSettingsService;
use App\Services\API\LeadCustomFieldService;
use App\Services\API\AcquisitionChannelService;
use App\DTO\Integration\CreateNewIntegrationZapierAppLeadDTO;


class CreateLeadFromZapierAppRequest extends APIBaseRequest
{

    private $acquisitionChannel;
    private $maxOtherFieldsCount = 10;
    private $maxCustomFieldsCount = 10;
    private $otherFieldsValidArr = [];
    private $customFieldsValidArr = [];
    


    public function rules()
    {
        $nullableStringOrNumeric = new StringOrNumeric(['nullable' => true]);
        $landingRegex = 'regex:/[(http(s)?):\/\/(www\.)?a-zA-Z0-9@:%._\+~#=]'.
            '{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&\/\/=]*)/'
        ;
        return [
            'lead' => ['required', 'array'],
            'lead.note' => ['sometimes', 'nullable', 'string'],
            'lead.name' => ['sometimes', 'nullable', 'string'],
            'lead.email' => ['sometimes', 'nullable', 'string'],
            'lead.company' => ['sometimes', 'nullable', 'string'],
            'lead.message' => ['sometimes', 'nullable', 'string'],
            'lead.lastName' => ['sometimes', 'nullable', 'string'],
            'lead.phone' => ['sometimes', $nullableStringOrNumeric],
            'lead.otherFields' => ['sometimes', 'nullable', 'array'],
            'lead.customFields' => ['sometimes', 'nullable', 'array'],
            'lead.landingUrl' => ['sometimes', 'nullable', $landingRegex],
            'lead.acquisitionChannel' => ['sometimes', 'nullable', 'string'],

            'lead.fbclid' => ['sometimes', 'nullable', 'string'],
            'lead.utm_source' => ['sometimes', 'nullable', 'string'],
            'lead.utm_medium' => ['sometimes', 'nullable', 'string'],
            'lead.utm_content' => ['sometimes', 'nullable', 'string'],
            'lead.utm_campaign' => ['sometimes', 'nullable', 'string'],
            'lead.utm_keywords' => ['sometimes', 'nullable', 'string'],
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

                $channelName = request()->input('lead.acquisitionChannel');
                if ($channelName) {
                    $this->acquisitionChannel = resolve(AcquisitionChannelService::class)->findOneByClientAndName(
                        $client, $channelName
                    );
                }

                $otherFields = request()->input('lead.otherFields');
                if ($otherFields) {
                    $otherFields = array_slice($otherFields, 0, $this->maxOtherFieldsCount);
                    foreach ($otherFields as $otherFieldName => $otherFieldVal) {
                        $otherFieldVal = trim($otherFieldVal);
                        $otherFieldName = trim($otherFieldName);
                        if (!$otherFieldVal) {
                            continue;
                        }
                        $this->otherFieldsValidArr[] = ['name' => $otherFieldName, 'value' => $otherFieldVal];
                    }
                }

                $customFields = request()->input('lead.customFields');
                if ($customFields) {
                    $customFields = array_slice($customFields, 0, $this->maxCustomFieldsCount);
                    $clientLeadCustomFields = resolve(LeadCustomFieldService::class)->findAllByClient($client);
                    foreach ($customFields as $customFieldName => $customFieldVal) {
                        if (!$customFieldVal) {
                            continue;
                        }
                        $customFieldName = trim(strtolower($customFieldName));
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
                                'leadCustomField' => $leadCustomField, 'value' => $customFieldVal
                            ];
                        }
                    }
                }
            }
        });
    }


    public function validatedDTO(): CreateNewIntegrationZapierAppLeadDTO
    {
        $validated = parent::validated();

        $client = request()->input('client');
        if (!$this->isValidEmail($validated['lead']['email'] ?? null)) {
            unset($validated['lead']['email']);
        }
        $validated['lead']['otherFields'] = $this->otherFieldsValidArr;
        $validated['lead']['customFields'] = $this->customFieldsValidArr;
        $validated['lead']['acquisitionChannel'] = $this->acquisitionChannel;
        $validated['lead']['phone'] = $this->sanitizePhoneNumber($validated['lead']['phone'] ?? null);
        
        $dto = CreateNewIntegrationZapierAppLeadDTO::buildFromRequestArray($validated);
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
        $name = request()->input('lead.name');
        $email = request()->input('lead.email');
        $phone = request()->input('lead.phone');
        $lastName = request()->input('lead.last_name');
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
        $leadAttrs['company'] = request()->input('lead.company') ?? null;
        $leadAttrs['message'] = request()->input('lead.message') ?? null;
        $mainLeadContactAttrs['name'] = request()->input('lead.name') ?? null;
        $mainLeadContactAttrs['email'] = request()->input('lead.email') ?? null;
        $mainLeadContactAttrs['last_name'] = request()->input('lead.lastName') ?? null;
        $mainLeadContactAttrs['phone'] = (request()->input('lead.phone'))
            ? $this->sanitizePhoneNumber(request()->input('lead.phone'))
            : null
        ;
        
        $client = request()->input('client');
        $hash = Lead::buildHash($leadAttrs, $mainLeadContactAttrs);
        $lead = resolve(LeadService::class)->findOneByClientAndHash($client, $hash);
        return $lead;
    }

}
