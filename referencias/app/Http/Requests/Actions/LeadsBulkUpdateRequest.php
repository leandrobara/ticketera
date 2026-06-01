<?php

namespace App\Http\Requests\Actions;

use App\Models\Tag;
use App\Models\Lead;
use App\Models\User;
use App\Models\Client;
use App\Models\LeadCustomField;
use App\Rules\IsArrayOfIntegers;
use App\Services\API\TagService;
use App\Services\API\UserService;
use App\Services\API\LeadService;
use App\Models\AcquisitionChannel;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\LeadCustomFieldService;
use App\DTO\Import\BulkUpload\CustomFieldDTO;
use App\Services\API\AcquisitionChannelService;
use App\DTO\Import\BulkUpdate\BulkUpdateLeadDataDTO;


class LeadsBulkUpdateRequest extends APIBaseRequest
{

    private $leadDBList;
    private $userDBList;
    private $channelDBList;
    private $leadCustomFieldDBList;


    public function rules()
    {
        return [
            'leads' => ['required', 'array'],
            'leads.*.lead_id' => ['required', 'integer'],
            'leads.*.user_id' => ['sometimes', 'integer'],
            'leads.*.notes' => ['sometimes', 'string'],
            'leads.*.company' => ['sometimes', 'string'],
            'leads.*.acquisition_channel_id' => ['sometimes', 'integer'],
            
            'leads.*.contacts' => ['sometimes', 'array'],
            'leads.*.contacts.*.phone' => ['sometimes', 'string'],
            'leads.*.contacts.*.email' => ['sometimes', 'string'],
            'leads.*.contacts.*.name' => ['sometimes', 'string'],
            'leads.*.contacts.*.last_name' => ['sometimes', 'string'],

            'leads.*.custom_fields' => ['sometimes', 'array'],
            'leads.*.custom_fields.*.value' => ['required_with:custom_fields', 'string'],
            'leads.*.custom_fields.*.lead_custom_field_id' => ['required_with:custom_fields', 'integer'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $client = request()->input('client');
                
                $leadsDataCollection = collect(request()->input('leads'));
                $notes = $leadsDataCollection->pluck('notes')->filter()->unique();
                $leadIds = $leadsDataCollection->pluck('lead_id')->filter()->unique();
                $company = $leadsDataCollection->pluck('company')->filter()->unique();
                $userIds = $leadsDataCollection->pluck('user_id')->filter()->unique();
                $leadsContacts = $leadsDataCollection->pluck('contacts')->filter()->unique();
                $customFields = $leadsDataCollection->pluck('custom_fields')->collapse()->unique();
                $channelIds = $leadsDataCollection->pluck('acquisition_channel_id')->filter()->unique();

                if (
                    $leadIds->isNotEmpty() &&
                    $notes->isEmpty() &&
                    $userIds->isEmpty() &&
                    $company->isEmpty() &&
                    $channelIds->isEmpty() &&
                    $customFields->isEmpty() &&
                    $leadsContacts->isEmpty()
                ) {
                    $validator->errors()->add('lead_attributes', 'some_attributes_must_be_completed');
                    return false;
                }

                $this->leadDBList = resolve(LeadService::class)->findByClientAndIds($client, $leadIds);
                $this->leadDBList = $this->leadDBList->keyBy('id');
                if ($leadIds->count() != $this->leadDBList->count()) {
                    $validator->errors()->add('lead_id', 'some_leads_do_not_exist');
                    return false;
                }

                if ($userIds->isNotEmpty()) {
                    $this->userDBList = resolve(UserService::class)->findByClientAndIds($client, $userIds->toArray());
                    $this->userDBList = $this->userDBList->keyBy('id');
                    if ($userIds->count() != $this->userDBList->count()) {
                        $validator->errors()->add('user_id', 'some_users_do_not_exist');
                        return false;
                    }
                }

                if ($channelIds->isNotEmpty()) {
                    $this->channelDBList = resolve(AcquisitionChannelService::class)->findByClientAndIds(
                        $client, $channelIds->toArray()
                    );
                    $this->channelDBList = $this->channelDBList->keyBy('id');
                    if ($channelIds->count() != $this->channelDBList->count()) {
                        $validator->errors()->add('acquisition_channel_id', 'some_channels_do_not_exist');
                        return false;
                    }
                }

                if ($customFields->isNotEmpty()) {
                    if (!$client->clientSettings->enable_leads_custom_fields) {
                        $validator->errors()->add('lead_custom_field', 'lead_custom_field_settings_is_not_enabled');
                        return false;
                    }

                    $this->leadCustomFieldDBList = resolve(LeadCustomFieldService::class)->findAllByClient($client);
                    $this->leadCustomFieldDBList = $this->leadCustomFieldDBList->keyBy('id');
                    foreach ($customFields as $i => $leadCustomField) {
                        $value = trim($leadCustomField['value']);
                        if (!$value && $value != '0') {
                            $validator->errors()->add('lead_custom_field', 'value_is_empty');
                            return false;
                        }

                        $leadCustomFieldId = trim($leadCustomField['lead_custom_field_id']);
                        $leadCustomField = $this->getCustomFieldById($leadCustomFieldId);
                        if (!$leadCustomField) {
                            $validator->errors()->add('lead_custom_field', 'lead_custom_field_does_not_exist');
                            return false;
                        }
                    }
                }

                foreach ($leadsDataCollection as $i => $leadData) {
                    $leadDB = $this->leadDBList->find($leadData['lead_id']);
                    $mainLeadContactData = $leadData['contacts'][0] ?? [];
                    $phoneValue = $mainLeadContactData['phone'] ?? null;
                    $emailValue = $mainLeadContactData['email'] ?? null;
                    $phoneValue = $phoneValue ? trim(strtolower($phoneValue)) : null;
                    $emailValue = $emailValue ? trim(strtolower($emailValue)) : null;

                    if ($phoneValue) {
                        $phoneExists = $leadDB->leadContactPhones
                            ->pluck('phone')
                            ->map(fn ($phoneDB) => trim(strtolower($phoneDB)))
                            ->contains($phoneValue)
                        ;
                        if ($phoneExists) {
                            $validator->errors()->add("lead_{$i}_phone", "lead_{$i}_phone_already_exists");
                            return false;
                        }
                    }

                    if ($emailValue) {
                        $emailExists = $leadDB->leadContactEmails
                            ->pluck('email')
                            ->map(fn ($emailDB) => trim(strtolower($emailDB)))
                            ->contains($emailValue)
                        ;
                        if (!$this->isValidEmail($emailValue)) {
                            $validator->errors()->add("lead_{$i}_email", "lead_{$i}_email_is_invalid");
                            return false;
                        }
                        if ($emailExists) {
                            $validator->errors()->add("lead_{$i}_email", "lead_{$i}_email_already_exists");
                            return false;
                        }
                    }
                }
            });
        }
    }


    public function validatedDTOs(): Collection
    {
        $dtoCollection = new Collection();
        $validated = parent::validated();

        foreach ($validated['leads'] as $leadData) {
            $userDB = null;
            $contacts = null;
            $channelDB = null;
            $customFieldDTOs = [];
            $notes = $leadData['notes'] ?? null;
            $company = $leadData['company'] ?? null;
            $leadDB = $this->getLeadById($leadData['lead_id']);

            if ($leadData['user_id'] ?? null) {
                $userDB = $this->getUserById($leadData['user_id']);
            }
            if ($leadData['acquisition_channel_id'] ?? null) {
                $channelDB = $this->getChannelById($leadData['acquisition_channel_id']);
            }
            
            if ($leadData['custom_fields'] ?? null) {
                $customFieldDTOs = collect($leadData['custom_fields'])->map(function ($customField) {
                    $leadCustomFieldDB = $this->getCustomFieldById($customField['lead_custom_field_id']);
                    return CustomFieldDTO::build($leadCustomFieldDB, $customField['value']);
                })->toArray();
            }

            $mainLeadContactData = $leadData['contacts'][0] ?? null;
            $params = [
                'lead' => $leadDB,
                'user' => $userDB,
                'notes' => $notes,
                'company' => $company,
                'acquisitionChannel' => $channelDB,
                'customFieldsDTOs' => $customFieldDTOs,
                'mainLeadContact' => $mainLeadContactData,
            ];

            $dto = BulkUpdateLeadDataDTO::build($params);
            $dtoCollection->push($dto);
        }

        return $dtoCollection;
    }


    protected function getLeadById(int $id): Lead
    {
        return $this->leadDBList->get($id);
    }
    
    
    protected function getUserById(int $id): User
    {
        return $this->userDBList->get($id);
    }


    protected function getCustomFieldById(int $id): LeadCustomField
    {
        return $this->leadCustomFieldDBList->get($id);
    }


    protected function getChannelById(int $id): AcquisitionChannel
    {
        return $this->channelDBList->get($id);
    }


    protected function isValidEmail(string $email): bool
    {
        $isValidEmail = filter_var($email, FILTER_VALIDATE_EMAIL) ? true : false;
        return $isValidEmail;
    }

}
