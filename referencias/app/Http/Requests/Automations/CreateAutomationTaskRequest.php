<?php

namespace App\Http\Requests\Automations;

use DateTime;
use DateTimeZone;
use App\Models\Tag;
use App\Models\Status;
use App\Models\TaskTemplate;
use App\Models\AutomationTask;
use App\Rules\IsArrayOfIntegers;
use App\Services\API\TagService;
use App\Services\API\StatusService;
use App\Http\Requests\APIBaseRequest;
use App\Services\API\TaskTemplateService;
use App\DTO\Automations\AutomationTaskDTO;
use App\Rules\InAutomationTaskReturnFields;
use App\Services\API\Automations\AutomationTaskService;


class CreateAutomationTaskRequest extends APIBaseRequest
{

    private $client = null;
    private $allowingTags = null;
    private $tagsToAssign = null;
    private $triggeringTags = null;
    private $cancellingTags = null;
    private $allowingStatus = null;
    private $statusToAssign = null;
    private $triggeringStatus = null;
    private $cancellingStatus = null;


    public function rules()
    {
        $opts = ['canBeEmpty' => true];
        $enabledTriggersRule = 'in:after_sale,after_status_change,after_tag_change,after_task_expiration';
        return [
            'enabled' => ['required', 'boolean'],
            'is_recurrent' => ['sometimes', 'boolean'],
            'task_template_id' => ['sometimes', 'integer'],
            'create_delay_days' => ['sometimes', 'integer'],
            'create_hour' => ['sometimes', 'date_format:H:i'],
            'status_id_to_assign' => ['sometimes', 'integer'],
            'is_immediately_created' => ['sometimes', 'boolean'],
            'trigger_type' => ['required', 'string', $enabledTriggersRule],
            'allowing_tags_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'tags_ids_to_assign' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'triggering_tags_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'cancelling_tags_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'allowing_status_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'cancelling_status_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'triggering_status_ids' => ['sometimes', 'array', new IsArrayOfIntegers($opts)],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InAutomationTaskReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $tagService = resolve(TagService::class);
                $statusService = resolve(StatusService::class);
                $taskTemplateService = resolve(TaskTemplateService::class);
                $automationTaskService = resolve(AutomationTaskService::class);

                $this->client = request()->input('client');
                $createHour = request()->input('create_hour');
                $triggerType = request()->input('trigger_type');
                $isRecurrent = request()->input('is_recurrent');
                $taskTemplateId = request()->input('task_template_id');
                $createDelayDays = request()->input('create_delay_days');
                $allowingTagsIds = request()->input('allowing_tags_ids');
                $tagsIdsToAssign = request()->input('tags_ids_to_assign');
                $triggeringTagIds = request()->input('triggering_tags_ids');
                $cancellingTagIds = request()->input('cancelling_tags_ids');
                $statusIdToAssign = request()->input('status_id_to_assign');
                $allowingStatusIds = request()->input('allowing_status_ids');
                $cancellingStatusIds = request()->input('cancelling_status_ids');
                $triggeringStatusIds = request()->input('triggering_status_ids');
                $isImmediatelyCreated = request()->input('is_immediately_created');

                $isAfterSaleTriggerType = $triggerType == 'after_sale';
                $isTagsTriggerType = $triggerType == 'after_tag_change';
                $isStatusTriggerType = $triggerType == 'after_status_change';
                $isAfterTaskExpirationTriggerType = $triggerType == 'after_task_expiration';

                if (!$isAfterTaskExpirationTriggerType) {
                    if (!$createHour) {
                        $validator->errors()->add('create_hour', 'create_hour_param_is_required');
                        return false;
                    }
                    if (!is_bool($isRecurrent)) {
                        $validator->errors()->add('is_recurrent', 'is_recurrent_param_is_required');
                        return false;
                    }
                    if (!$taskTemplateId) {
                        $validator->errors()->add('task_template_id', 'task_template_id_param_is_required');
                        return false;
                    }
                    if (!$createDelayDays) {
                        $validator->errors()->add('create_delay_days', 'create_delay_days_param_is_required');
                        return false;
                    }
                    if ($allowingTagsIds) {
                        $validator->errors()->add(
                            'allowing_tags_ids', 'param_only_allowed_with_after_task_expiration_trigger'
                        );
                        return false;
                    }
                    if ($allowingStatusIds) {
                        $validator->errors()->add(
                            'allowing_status_ids', 'param_only_allowed_with_after_task_expiration_trigger'
                        );
                        return false;
                    }
                    if ($tagsIdsToAssign) {
                        $validator->errors()->add(
                            'tags_ids_to_assign', 'param_only_allowed_with_after_task_expiration_trigger'
                        );
                        return false;
                    }
                    if ($statusIdToAssign) {
                        $validator->errors()->add(
                            'status_id_to_assign', 'param_only_allowed_with_after_task_expiration_trigger'
                        );
                        return false;
                    }
                    if (!is_bool($isImmediatelyCreated)) {
                        $validator->errors()->add(
                            'is_immediately_created', 'is_immediately_created_param_is_required'
                        );
                        return false;
                    }

                    $existsTaskTemplate = $taskTemplateService->findOneByTaskTemplateIdAndClient(
                        $taskTemplateId, $this->client
                    );
                    if (!$existsTaskTemplate) {
                        $validator->errors()->add('task_template', 'task_template_does_not_exist');
                        return false;
                    }

                    if ($isTagsTriggerType && !$triggeringTagIds) {
                        $validator->errors()->add('triggering_tags_ids', 'triggering_tags_can_not_be_empty');
                        return false;
                    }

                    if ($isStatusTriggerType && !$triggeringStatusIds) {
                        $validator->errors()->add('triggering_status_ids', 'triggering_status_can_not_be_empty');
                        return false;
                    }

                    if ($triggeringStatusIds && $triggeringTagIds) {
                        $validator->errors()->add(
                            'triggering_ids', 'can_not_use_triggering_tags_and_status_at_the_same_time'
                        );
                        return false;
                    }

                    if ($isTagsTriggerType) {
                        $tags = $tagService->findByClientAndIds($this->client, $triggeringTagIds);
                        if ($tags->count() != count($triggeringTagIds)) {
                            $validator->errors()->add('triggering_tags_ids', 'not_all_triggering_tags_exists');
                            return false;
                        }
                        $allTagTriggeringAutomationsFromClient = $automationTaskService->findByClientAndTrigger(
                            $this->client, 'after_tag_change'
                        );
                        if (
                            $allTagTriggeringAutomationsFromClient->pluck('triggering_tags_ids')
                                ->flatten()
                                ->filter(null)
                                ->intersect($triggeringTagIds)
                                ->isNotEmpty()
                        ) {
                            $validator->errors()->add(
                                'triggering_tags_ids', 'triggering_tags_ids_are_being_used_by_another_automation'
                            );
                            return false;
                        }
                        $this->triggeringTags = $tags;
                    }

                    if ($isStatusTriggerType) {
                        $triggeringStatus = $statusService->findByClientAndIds($this->client, $triggeringStatusIds);
                        if ($triggeringStatus->count() != count($triggeringStatusIds)) {
                            $validator->errors()->add('triggering_status_ids', 'not_all_triggering_status_exists');
                            return false;
                        }
                        $allStatusTriggeringAutomationsFromClient = $automationTaskService->findByClientAndTrigger(
                            $this->client, 'after_status_change'
                        );
                        if (
                            $allStatusTriggeringAutomationsFromClient->pluck('triggering_status_ids')
                                ->flatten()
                                ->filter(null)
                                ->intersect($triggeringStatusIds)
                                ->isNotEmpty()
                        ) {
                            $validator->errors()->add(
                                'triggering_tags_ids', 'triggering_status_ids_are_being_used_by_another_automation'
                            );
                            return false;
                        }
                        $this->triggeringStatus = $triggeringStatus;
                    }
                }


                if ($isAfterTaskExpirationTriggerType) {
                    if ($createHour) {
                        $validator->errors()->add(
                            'create_hour', 'param_not_allowed_with_after_task_expiration_trigger'
                        );
                        return false;
                    }
                    if ($isRecurrent) {
                        $validator->errors()->add(
                            'is_recurrent', 'param_not_allowed_with_after_task_expiration_trigger'
                        );
                        return false;
                    }
                    if ($taskTemplateId) {
                        $validator->errors()->add(
                            'task_template_id', 'param_not_allowed_with_after_task_expiration_trigger'
                        );
                        return false;
                    }
                    if ($createDelayDays) {
                        $validator->errors()->add(
                            'create_delay_days', 'param_not_allowed_with_after_task_expiration_trigger'
                        );
                        return false;
                    }
                    if ($isImmediatelyCreated) {
                        $validator->errors()->add(
                            'is_immediately_created', 'param_not_allowed_with_after_task_expiration_trigger'
                        );
                        return false;
                    }
                    if ($triggeringTagIds) {
                        $validator->errors()->add(
                            'triggering_tags_ids', 'param_not_allowed_with_after_task_expiration_trigger'
                        );
                        return false;
                    }
                    if ($triggeringStatusIds) {
                        $validator->errors()->add(
                            'triggering_status_ids', 'param_not_allowed_with_after_task_expiration_trigger'
                        );
                        return false;
                    }
                    if (!$tagsIdsToAssign && !$statusIdToAssign) {
                        $validator->errors()->add(
                            'tag_status_ids_to_assign', 'one_of_both_param_required_with_after_task_expiration_trigger'
                        );
                        return false;
                    }

                    if ($tagsIdsToAssign) {
                        $tagsToAssign = $tagService->findByClientAndIds($this->client, $tagsIdsToAssign);
                        if ($tagsToAssign->count() != count($tagsIdsToAssign)) {
                            $validator->errors()->add('tags_ids_to_assign', 'not_all_tags_exists');
                            return false;
                        }
                        $this->tagsToAssign = $tagsToAssign;
                    }
                    if ($statusIdToAssign) {
                        $statusToAssign = $statusService->findOneByClientAndId($this->client, $statusIdToAssign);
                        if (!$statusToAssign) {
                            $validator->errors()->add('status_id_to_assign', 'status_does_not_exist');
                            return false;
                        }
                        $this->statusToAssign = $statusToAssign;
                    }

                    if ($allowingTagsIds) {
                        $allowingTags = $tagService->findByClientAndIds($this->client, $allowingTagsIds);
                        if ($allowingTags->count() != count($allowingTagsIds)) {
                            $validator->errors()->add('tags_ids_to_assign', 'not_all_tags_exist');
                            return false;
                        }
                        $this->allowingTags = $allowingTags;
                    }
                    if ($allowingStatusIds) {
                        $allowingStatus = $statusService->findByClientAndIds($this->client, $allowingStatusIds);
                        if ($allowingStatus->count() != count($allowingStatusIds)) {
                            $validator->errors()->add('status_ids_to_assign', 'not_all_status_exist');
                            return false;
                        }
                        $this->allowingStatus = $allowingStatus;
                    }
                }

                if ($cancellingTagIds) {
                    $tags = $tagService->findByClientAndIds($this->client, $cancellingTagIds);
                    if ($tags->count() != count($cancellingTagIds)) {
                        $validator->errors()->add('cancelling_tags_ids', 'not_all_cancelling_tags_exists');
                        return false;
                    }
                    $this->cancellingTags =  $tags;
                }
                if ($cancellingStatusIds) {
                    $cancellingStatus = $statusService->findByClientAndIds($this->client, $cancellingStatusIds);
                    if ($cancellingStatus->count() != count($cancellingStatusIds)) {
                        $validator->errors()->add('cancelling_status_ids', 'not_all_cancelling_status_exists');
                        return false;
                    }
                    $this->cancellingStatus = $cancellingStatus;
                }
            }
        });
    }


    private function getFormattedCreateTaskHourByTimeZone(string $createTaskHour): string
    {
        $tz = request()->input('client')->timezone;
        $sendHourArr = explode(':', $createTaskHour);
        $hour = (int) $sendHourArr[0];
        $minutes = (int) $sendHourArr[1];
        // Set date (with hour and minute) with Client TZ
        $date = (new DateTime())->setTimezone(new DateTimeZone($tz))->setTime($hour, $minutes, 0);
        // Convert client's TZ to UTC0
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date->format('H:i');
    }


    public function validatedDTO(): AutomationTaskDTO
    {
        $triggerType = request()->input('trigger_type');
        $isAfterTaskExpirationTriggerType = $triggerType == 'after_task_expiration';

        $validated = parent::validated();
        $validated['client'] = $this->client;
        $validated['cancellingTags'] = $this->cancellingTags;
        $validated['cancellingStatus'] = $this->cancellingStatus;

        if ($isAfterTaskExpirationTriggerType) {
            $validated['allowingTags'] = $this->allowingTags;
            $validated['tagsToAssign'] = $this->tagsToAssign;
            $validated['allowingStatus'] = $this->allowingStatus;
            $validated['statusToAssign'] = $this->statusToAssign;
        } else {
            $validated['triggeringTags'] = $this->triggeringTags;
            $validated['triggeringStatus'] = $this->triggeringStatus;
            $validated['create_hour'] = $this->getFormattedCreateTaskHourByTimeZone($validated['create_hour']);
        }

        return AutomationTaskDTO::build($validated);
    }

}
