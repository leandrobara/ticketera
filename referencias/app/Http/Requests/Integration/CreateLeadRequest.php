<?php

namespace App\Http\Requests\Integration;

use App\Models\Lead;
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
use App\DTO\Integration\CreateNewIntegrationLeadDTO;


class CreateLeadRequest extends APIBaseRequest
{

    private $otherFieldsValidArr = [];
    private $maxCustomFieldsCount = 10;
    private $customFieldsValidArr = [];
    private $methodIntegration = 'form';
    private $acquisitionChannelId = null;


    public function rules()
    {
        $nullableStringOrNumeric = new StringOrNumeric(['nullable' => true]);
        $landingRegex = 'regex:/[(http(s)?):\/\/(www\.)?a-zA-Z0-9@:%._\+~#=]'.
            '{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&\/\/=]*)/'
        ;
        return [
            'lead' => ['required', 'array'],
            'lead.company' => ['sometimes', 'nullable', 'string'],
            'lead.name' => ['sometimes', 'nullable', 'string'],
            'lead.email' => ['sometimes', 'nullable', 'email'],
            'lead.phone' => ['sometimes', 'nullable', 'string'],
            'lead.email2' => ['sometimes', 'nullable', 'email'],
            'lead.phone2' => ['sometimes', 'nullable', 'string'],
            'lead.message' => ['sometimes', 'nullable', 'string'],
            'lead.lastName' => ['sometimes', 'nullable', 'string'],
            'lead.otherFields' => ['sometimes', 'nullable', 'array'],
            'lead.customFields' => ['sometimes', 'nullable', 'array'],
            'lead.acquisitionChannel' => ['sometimes', 'nullable', 'array'],
            'lead.acquisitionChannel.name' => ['sometimes', $nullableStringOrNumeric],
            'lead.landing' => ['sometimes', 'nullable', 'array'],
            'lead.landing.url' => ['sometimes', $landingRegex],
            'lead.notes' => ['sometimes', 'array'],
            'lead.notes.*.note' => ['sometimes', 'nullable', 'string'],

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
                $landing = request()->input('lead.landing');
                $otherFields = request()->input('lead.otherFields');
                $customFields = request()->input('lead.customFields');
                $channel = request()->input('lead.acquisitionChannel');
                $channelName = $channel ? ($channel['name'] ?? null) : null;
                
                if ($this->leadIsEmpty()) {
                    $validator->errors()->add('lead', 'lead_main_data_is_empty');
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

                $existentLead = $this->findExistentLead();
                if ($existentLead) {
                    $validator->errors()->add('lead', 'lead_already_exists');
                    return false;
                }

                if ($customFields) {
                    $customFields = array_slice($customFields, 0, 5);
                    $clientLeadCustomFields = resolve(LeadCustomFieldService::class)->findAllByClient();
                    foreach ($customFields as $customField) {
                        $customFieldVal = $customField['value'];
                        $customFieldName = trim($customField['name']);
                        if (!$customFieldVal || !$customFieldName) {
                            continue;
                        }
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

                if ($otherFields) {
                    $otherFields = array_slice($otherFields, 0, 12);
                    foreach ($otherFields as $otherField) {
                        $otherFieldVal = trim($otherField['value']);
                        $otherFieldName = trim($otherField['name']);
                        if (!$otherFieldVal || !$otherFieldName) {
                            continue;
                        }
                        $this->otherFieldsValidArr[] = ['name' => $otherFieldName, 'value' => $otherFieldVal];
                    }
                }

                if ($landing && !key_exists('url', $landing)) {
                    $validator->errors()->add('landing_url', 'the_landing_url_field_must_be_present');
                    return false;
                }
            }
        });
    }


    public function validatedDTO(): CreateNewIntegrationLeadDTO
    {
        $validated = parent::validated();

        if ($validated['lead']['phone'] ?? null) {
            $validated['lead']['phone'] = $this->sanitizePhoneNumber($validated['lead']['phone']);
        }
        if ($validated['lead']['phone2'] ?? null) {
            $validated['lead']['phone2'] = $this->sanitizePhoneNumber($validated['lead']['phone2']);
        }

        $noteArr = collect($validated['lead']['notes'] ?? [])->first();
        $noteText = $noteArr['note'] ?? null;
        if ($noteText) {
            $validated['lead']['notes'] = [$noteText];
        }

        $validated['lead']['isFromIntegrationApi'] = true;
        $validated['lead']['isFromZapierWebhook'] = false;
        $validated['lead']['method'] = $this->methodIntegration;
        $validated['lead']['otherFields'] = $this->otherFieldsValidArr;
        $validated['lead']['clientId'] = request()->input('client')->id;
        $validated['lead']['customFields'] = $this->customFieldsValidArr;
        $validated['lead']['acquisitionChannelId'] = $this->acquisitionChannelId;

        $dto = CreateNewIntegrationLeadDTO::buildFromRequestArray($validated);
        return $dto;
    }


    private function sanitizePhoneNumber(string $phone): string
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
        $client = request()->input('client');
        $leadAttrs['method'] = $this->methodIntegration;
        $leadAttrs['company'] = request()->input('lead.company') ?? null;
        $leadAttrs['message'] = request()->input('lead.message') ?? null;
        $mainLeadContactAttrs['name'] = request()->input('lead.name') ?? null;
        $mainLeadContactAttrs['email'] = request()->input('lead.email') ?? null;
        $mainLeadContactAttrs['last_name'] = request()->input('lead.lastName') ?? null;
        $mainLeadContactAttrs['phone'] = (request()->input('lead.phone'))
            ? $this->sanitizePhoneNumber(request()->input('lead.phone'))
            : null
        ;
        
        $hash = Lead::buildHash($leadAttrs, $mainLeadContactAttrs);
        $lead = resolve(LeadService::class)->findOneByClientAndHash($client, $hash);
        return $lead;
    }

}
