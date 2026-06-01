<?php

namespace App\Http\Requests;

use App\Models\Tag;
use App\Models\Lead;
use App\Services\API\LeadService;
use App\Rules\InLeadReturnFields;
use App\Models\AcquisitionChannel;
use App\DTO\CreateNewManualLeadDTO;
use App\Services\API\LeadCustomFieldService;

class CreateLeadRequest extends APIBaseRequest
{

    private $tags = [];
    private $notes = [];
    private $customFields = [];
    private $dto;
    
    public function rules()
    {
        return [
            'user_id' => ['required', 'integer'],
            'status_id' => ['required', 'integer'],
            'tag_id' => ['sometimes', 'array'],
            'tag_id.*' => ['sometimes', 'integer'],
            'acquisition_channel_id' => ['required', 'integer'],
            'company' => ['sometimes', 'nullable', 'string'],
            'message' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'array'],
            'notes.*' => ['sometimes', 'string'],
            'custom_fields' => ['sometimes', 'array'],
            'custom_fields.*.value' => ['required_with:custom_fields', 'string'],
            'custom_fields.*.lead_custom_field_id' => ['required_with:custom_fields', 'integer'],
            'mainLeadContact' => ['required', 'array'],
            'mainLeadContact.name' => ['sometimes', 'nullable', 'string'],
            'mainLeadContact.last_name' => ['sometimes', 'nullable', 'string'],
            'mainLeadContact.email' => ['sometimes', 'nullable', 'email'],
            'mainLeadContact.email2' => ['sometimes', 'nullable', 'email'],
            'mainLeadContact.phone' => ['sometimes', 'nullable', 'string'],
            'mainLeadContact.phone2' => ['sometimes', 'nullable', 'string'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', 'string', new InLeadReturnFields()]
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $tagIds = request()->input('tag_id');
                $mainLeadContact = request()->input('mainLeadContact');
                $channelId = request()->input('acquisition_channel_id');

                if ($tagIds) {
                    $this->tags = Tag::where('client_id', $client->id)->whereIn('id', $tagIds)->get();
                    foreach ($this->tags as $tag) {
                        if ($tag['client_id'] != $client->id) {
                            $validator->errors()->add(
                                'tag_id', 'tag_client_does_not_match_with_authenticated_client'
                            );
                            return false;
                        }
                    }
                }

                if ($channelId) {
                    $existentChannel = AcquisitionChannel::where('client_id', $client->id)
                        ->where('id', $channelId)
                        ->first()
                    ;
                    if (!$existentChannel) {
                        $validator->errors()->add('acquisition_channel_id', 'acquisition_channel_id_does_not_exist');
                        return false;
                    }
                }

                if (!$mainLeadContact['name'] && !$mainLeadContact['email']) {
                    $validator->errors()->add('main_lead_contact', 'name_or_email_must_be_present');
                    return false;
                }

                $customFields = request()->input('custom_fields');
                if ($customFields) {
                    $leadCustomFields = resolve(LeadCustomFieldService::class)->findAllByClient();
                    foreach ($customFields as $customField) {
                        $value = trim($customField['value']);
                        if ($value !== '0' && !$value) {
                            $validator->errors()->add('lead_custom_field', 'value_is_empty');
                            return false;
                        }
                        $leadCustomField = $leadCustomFields
                            ->where('id', $customField['lead_custom_field_id'])
                            ->where('client_id', $client->id)
                            ->first()
                        ;
                        if (!$leadCustomField) {
                            $validator->errors()->add('lead_custom_field', 'lead_custom_field_does_not_exist');
                            return false;
                        }
                        $this->customFields[] = ['leadCustomField' => $leadCustomField, 'value' => $value];
                    }
                }

                $notes = request()->input('notes');
                if ($notes) {
                    $this->notes = $notes;
                }

                $validated = parent::validated();
                $this->dto = CreateNewManualLeadDTO::buildFromRequestArray($validated);
                $leadAttrs = $this->dto->getNewLeadAttrs();
                $leadAttrs['method'] = 'form';
                $mainLeadContactAttrs = $this->dto->getMainLeadContactAttrs();
                $leadHash = Lead::buildHash($leadAttrs, $mainLeadContactAttrs);
                $existentLead = resolve(LeadService::class)->findOneByClientAndHash($client, $leadHash);
                if ($existentLead) {
                    $validator->errors()->add('lead_create', 'lead_already_exists');
                    return false;
                }
            }
        });
    }


    public function validatedDTO(): CreateNewManualLeadDTO
    {
        $this->dto->setTags(collect($this->tags));
        $this->dto->setNotes(collect($this->notes));
        $this->dto->setCustomFields(collect($this->customFields));
        return $this->dto;
    }

}
