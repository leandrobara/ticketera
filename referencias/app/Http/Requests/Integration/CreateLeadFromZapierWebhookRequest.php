<?php

namespace App\Http\Requests\Integration;

use App\Models\Lead;
use Illuminate\Support\Str;
use App\Rules\StringOrNumeric;
use App\Services\API\UserService;
use App\Services\API\LeadService;
use App\Services\API\StatusService;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\LeadCustomFieldService;
use App\Services\API\AcquisitionChannelService;
use App\DTO\Integration\CreateNewIntegrationLeadDTO;


class CreateLeadFromZapierWebhookRequest extends APIBaseRequest
{

    private $validCustomFields = [];
    private $maxCustomFieldsCount = 10;
    private $methodIntegration = 'form';
    private $acquisitionChannelId = null;


    public function rules()
    {
        $nullableStringOrNumeric = new StringOrNumeric(['nullable' => true]);
        return [
            'lead' => ['required', 'array'],
            'lead.company' => ['sometimes', 'nullable', 'string'],
            'lead.name' => ['sometimes', 'nullable', 'string'],
            'lead.email' => ['sometimes', 'nullable', 'email'],
            'lead.phone' => ['sometimes', $nullableStringOrNumeric],
            'lead.email2' => ['sometimes', 'nullable', 'email'],
            'lead.phone2' => ['sometimes', $nullableStringOrNumeric],
            'lead.message' => ['sometimes', 'nullable', 'string'],
            'lead.lastName' => ['sometimes', 'nullable', 'string'],
            'lead.customFields' => ['sometimes', 'nullable', 'array'],
            'lead.customFields.*.name' => ['sometimes', 'string'],
            'lead.customFields.*.value' => ['sometimes', $nullableStringOrNumeric],
            'lead.otherFields' => ['sometimes', 'nullable', 'array'],
            'lead.otherFields.*.name' => ['sometimes', 'string'],
            'lead.otherFields.*.value' => ['sometimes', $nullableStringOrNumeric],
            'lead.acquisitionChannel' => ['sometimes', 'nullable', 'array'],
            'lead.acquisitionChannel.name' => ['sometimes', $nullableStringOrNumeric],
            'lead.notes' => ['sometimes', 'array'],
            'lead.notes.*.note' => ['sometimes', 'nullable', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
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
                    $customFields = array_slice($customFields, 0, 3);

                    $clientLeadCustomFields = resolve(LeadCustomFieldService::class)->findAllByClient();
                    foreach ($customFields as $customField) {
                        $customFieldVal = $customField['value'];
                        if (!$customFieldVal) {
                            continue;
                        }

                        $customFieldName = trim($customField['name']);
                        $leadCustomField = $clientLeadCustomFields
                            ->where('name', $customFieldName)
                            ->where('client_id', $client->id)
                            ->first()
                        ;
                        if (!$leadCustomField) {
                            $validator->errors()->add('lead_custom_field', 'lead_custom_field_does_not_exist');
                            return false;
                        }
                        $this->validCustomFields[] = [
                            'value' => $customFieldVal, 'leadCustomField' => $leadCustomField,
                        ];
                    }
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

        $notes = collect($validated['lead']['notes'] ?? []);
        $noteArr = $notes->first();
        $noteText = $noteArr['note'] ?? null;
        if ($noteText) {
            $validated['lead']['notes'] = [$noteText];
        }

        $validated['lead']['isFromIntegrationApi'] = true;
        $validated['lead']['isFromZapierWebhook'] = true;
        $validated['lead']['method'] = $this->methodIntegration;
        $validated['lead']['customFields'] = $this->validCustomFields;
        $validated['lead']['clientId'] = request()->input('client')->id;
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
