<?php

namespace App\Http\Requests\Actions;

use App\Models\Tag;
use App\Models\User;
use App\Models\Status;
use App\Models\Client;
use App\Models\LeadCustomField;
use App\Rules\IsArrayOfIntegers;
use App\Services\API\TagService;
use App\Services\API\UserService;
use App\Models\AcquisitionChannel;
use Illuminate\Support\Collection;
use App\Services\API\StatusService;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\LeadCustomFieldService;
use App\DTO\Import\BulkUpload\CustomFieldDTO;
use App\Services\API\AcquisitionChannelService;
use App\DTO\Import\BulkUpload\BulkUploadLeadDataDTO;


class LeadsBulkUploadRequest extends APIBaseRequest
{

    private $tagList = [];
    private $userList = [];
    private $statusList = [];
    private $channelList = [];
    private $customFieldList = [];


    public function rules()
    {
        set_time_limit(120);
        
        return [
            'leads' => ['required', 'array'],
            'leads.*.tag_ids' => ['present', 'array'],
            'leads.*.user_id' => ['sometimes', 'nullable', 'integer'],
            'leads.*.status_id' => ['required', 'nullable', 'integer'],
            'leads.*.tag_ids.*' => ['sometimes', 'integer'],
            // 'leads.*.new_tag_names' => ['required', 'array'],
            // 'leads.*.new_tag_names.*' => ['sometimes', 'string'],
            'leads.*.notes' => ['sometimes', 'nullable', 'string'],
            'leads.*.company' => ['present', 'nullable', 'string'],
            'leads.*.message' => ['present', 'nullable', 'string'],
            // 'leads.*.new_status_name' => ['sometimes', 'nullable', 'string'],
            'leads.*.acquisition_channel_id' => ['sometimes', 'nullable', 'integer'],
            // 'leads.*.new_acquisition_channel_name' => ['sometimes', 'nullable', 'string'],
            
            'leads.*.contacts' => ['required', 'array'],
            'leads.*.contacts.*.phones' => ['sometimes', 'array'],
            'leads.*.contacts.*.emails' => ['sometimes', 'array'],
            'leads.*.contacts.*.emails.*' => ['sometimes', 'email'],
            'leads.*.contacts.*.phones.*' => ['sometimes', 'string'],
            'leads.*.contacts.*.name' => ['sometimes', 'nullable', 'string'],
            'leads.*.contacts.*.last_name' => ['sometimes', 'nullable', 'string'],

            'leads.*.custom_fields' => ['sometimes', 'array'],
            'leads.*.custom_fields.*.value' => ['required_with:custom_fields', 'string'],
            'leads.*.custom_fields.*.lead_custom_field_id' => ['required_with:custom_fields', 'integer'],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $this->tagList = collect([]);
                $this->userList = collect([]);
                $this->statusList = collect([]);
                $this->channelList = collect([]);


                $client = request()->input('client');
                $leadsDataCollection = collect(request()->input('leads'));

                $tagIds = $leadsDataCollection->map(function ($leadData) {
                    return $leadData['tag_ids'];
                })->flatten()->unique();

                if ($tagIds->isNotEmpty()) {
                    $this->tagList = resolve(TagService::class)->findByClientAndIds(
                        $client, $tagIds->toArray()
                    )->keyBy('id');
                    if ($tagIds->count() != $this->tagList->count()) {
                        $validator->errors()->add('tag_ids', 'some_tags_do_not_exist');
                        return false;
                    }
                }

                $statusIds = $leadsDataCollection->map(function ($leadData) {
                    return $leadData['status_id'];
                })->flatten()->unique()->filter();

                if ($statusIds->isNotEmpty()) {
                    $this->statusList = resolve(StatusService::class)->findByClientAndIds(
                        $client, $statusIds->toArray()
                    )->keyBy('id');
                    if ($statusIds->count() != $this->statusList->count()) {
                        $validator->errors()->add('status_id', 'some_status_do_not_exist');
                        return false;
                    }
                }

                $userIds = $leadsDataCollection->map(function ($leadData) {
                    return $leadData['user_id'];
                })->flatten()->unique()->filter();

                if ($userIds->isNotEmpty()) {
                    $this->userList = resolve(UserService::class)->findByClientAndIds(
                        $client, $userIds->toArray()
                    )->keyBy('id');
                    if ($userIds->count() != $this->userList->count()) {
                        $validator->errors()->add('user_id', 'some_users_do_not_exist');
                        return false;
                    }
                }

                $channelIds = $leadsDataCollection->map(function ($leadData) {
                    return $leadData['acquisition_channel_id'];
                })->flatten()->unique()->filter();

                if ($channelIds->isNotEmpty()) {
                    $this->channelList = resolve(AcquisitionChannelService::class)->findByClientAndIds(
                        $client, $channelIds->toArray()
                    )->keyBy('id');
                    if ($channelIds->count() != $this->channelList->count()) {
                        $validator->errors()->add('acquisition_channel_id', 'some_channels_do_not_exist');
                        return false;
                    }
                }

                $leadsContacts = $leadsDataCollection->map(function ($leadData) {
                    return $leadData['contacts'];
                });
                foreach ($leadsContacts as $i => $leadContacts) {
                    $mainContact = $leadContacts[0] ?? [];
                    if (!$mainContact) {
                        $validator->errors()->add('lead_' . $i . '_contacts', 'main_contact_do_not_exist');
                        return false;
                    }
                    $mainContactIsEmpty = array_filter($mainContact);
                    if (!$mainContactIsEmpty) {
                        $validator->errors()->add('lead_' . $i . '_contacts', 'main_contact_is_empty');
                        return false;
                    }
                }

                $customFields = [];
                foreach ($leadsDataCollection as $leadData) {
                    foreach (($leadData['custom_fields'] ?? []) as $customField) {
                        $customFields[] = $customField;
                    }
                }
                if ($customFields) {
                    $this->customFieldList = resolve(LeadCustomFieldService::class)->findAllByClient($client);

                    foreach ($customFields as $i => $customField) {
                        $leadCustomFieldId = trim($customField['lead_custom_field_id']);
                        $leadCustomField = $this->getCustomFieldByClientAndId($client, $leadCustomFieldId);
                        if (!$leadCustomField) {
                            $validator->errors()->add('lead_custom_field', 'lead_custom_field_does_not_exist');
                            return false;
                        }
                    }
                }
            });
        }
    }


    public function validatedDTOs(): Collection
    {
        $dtoCollection = collect([]);
        $validated = parent::validated();

        foreach ($validated['leads'] as $leadData) {
            $tags = $this->getTagsByIds($leadData['tag_ids']);
            $status = $this->getStatusById($leadData['status_id']);
            $channel = $this->getChannelById($leadData['acquisition_channel_id']);
            $user = $leadData['user_id'] ? $this->getUserById($leadData['user_id']) : null;
            $contacts = array_filter($leadData['contacts'], function ($leadContact) {
                return array_filter($leadContact);
            });

            $customFields = collect($leadData['custom_fields'] ?? [])
                ->filter(function ($customField) {
                    return (trim($customField['value']) !== '');
                })
                ->map(function ($customField) {
                    $client = request()->input('client');
                    $leadCustomField = $this->getCustomFieldByClientAndId(
                        $client, $customField['lead_custom_field_id']
                    );
                    return CustomFieldDTO::build($leadCustomField, $customField['value']);
                })
                ->toArray()
            ;
            
            $params = [
                'tags' => $tags,
                'user' => $user,
                'status' => $status,
                'contacts' => $contacts,
                'notes' => $leadData['notes'],
                'customFields' => $customFields,
                'acquisitionChannel' => $channel,
                'company' => $leadData['company'],
                'message' => $leadData['message'],
            ];
            $dto = BulkUploadLeadDataDTO::build($params);
            $dtoCollection->push($dto);
        }
        return $dtoCollection;
    }


    protected function getTagsByIds(?array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $tags = array_map(function ($id) {
            return $this->tagList->get($id);
        }, $ids);
        return $tags;
    }


    protected function getUserById(?int $id): ?User
    {
        return $this->userList->get($id);
    }


    protected function getCustomFieldByClientAndId(Client $client, int $id): ?LeadCustomField
    {
        return $this->customFieldList->where('id', $id)->where('client_id', $client->id)->first();
    }


    protected function getStatusById(?int $id): ?Status
    {
        if (!$id) {
            return null;
        }
        return $this->statusList->get($id);
    }


    protected function getChannelById(?int $id): ?AcquisitionChannel
    {
        if (!$id) {
            return null;
        }
        return $this->channelList->get($id);
    }

}
