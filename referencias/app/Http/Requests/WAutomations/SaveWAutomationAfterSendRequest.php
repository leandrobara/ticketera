<?php

namespace App\Http\Requests\WAutomations;

use DateTime;
use DateTimeZone;
use App\Services\API\TagService;
use App\Services\API\StatusService;
use App\Http\Requests\APIBaseRequest;
use App\DTO\WAutomations\WAutomationAfterSendDTO;
use App\Rules\InWAutomationAfterSendReturnFields;


class SaveWAutomationAfterSendRequest extends APIBaseRequest
{

    private $tagsToAdd = [];
    private $tagsToRemove = [];
    private $statusToAssign = null;


    public function rules()
    {
        return [
            'enabled' => ['required', 'boolean'],
            'add_new_note' => ['present', 'boolean'],
            'apply_only_once' => ['present', 'boolean'],
            'add_tags_ids' => ['present', 'nullable', 'array'],
            'new_note_text' => ['present', 'nullable', 'string'],
            'remove_tags_ids' => ['present', 'nullable', 'array'],
            'assign_status_id' => ['present', 'integer', 'nullable'],
            'only_apply_to_massive_sendings' => ['present', 'boolean'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InWAutomationAfterSendReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $tagsToAddIds = request()->input('add_tags_ids');
                $tagsToRemoveIds = request()->input('remove_tags_ids');
                $statusToAssignId = request()->input('assign_status_id');

                if ($tagsToAddIds) {
                    $tags = resolve(TagService::class)->findByClientAndIds($client, $tagsToAddIds);
                    if ($tags->count() != count($tagsToAddIds)) {
                        $validator->errors()->add('add_tags_ids', 'not_all_tags_exist');
                        return false;
                    }
                    $this->tagsToAdd = $tags;
                }

                if ($tagsToRemoveIds) {
                    $tags = resolve(TagService::class)->findByClientAndIds($client, $tagsToRemoveIds);
                    if ($tags->count() != count($tagsToRemoveIds)) {
                        $validator->errors()->add('remove_tags_ids', 'not_all_tags_exist');
                        return false;
                    }
                    $this->tagsToRemove = $tags;
                }

                if ($statusToAssignId) {
                    $status = resolve(StatusService::class)->find($statusToAssignId);
                    if (!$status || $status->client_id != $client->id) {
                        $validator->errors()->add('assign_status_id', 'assign_status_does_not_exists');
                        return false;
                    }
                    $this->statusToAssign = $status;
                }
            }
        });
    }


    public function validatedDTO(): WAutomationAfterSendDTO
    {
        $val = parent::validated();
        $dto = new WAutomationAfterSendDTO(
            enabled: $val['enabled'],
            addNewNote: $val['add_new_note'],
            client: request()->input('client'),
            newNoteText: $val['new_note_text'],
            tagsToAdd: collect($this->tagsToAdd),
            statusToAssign: $this->statusToAssign,
            applyOnlyOnce: $val['apply_only_once'],
            tagsToRemove: collect($this->tagsToRemove),
            onlyApplyToMassiveSendings: $val['only_apply_to_massive_sendings'],
        );
        return $dto;
    }

}
