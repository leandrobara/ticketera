<?php

namespace App\Http\Resources\Views\AutomationLogList;

use App\Models\User;
use App\Http\Resources\EmailTemplateResource;
use App\Http\Resources\UserResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class AutomationNewLeadResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        // this is restricted to prevent data leakage
        $visibleFields = $this->getFieldsToShow();
        if (!$visibleFields) {
            $response = [
                'id' => $this->resource->id,
                'created_at' => $this->resource->created_at,
                'new_note_text' => $this->resource->new_note_text,
                'new_task_title' => $this->resource->new_task_title,
                'assign_quality' => $this->resource->assign_quality,
                'new_task_description' => $this->resource->new_task_description,
                'triggering_lead_type' => $this->resource->triggering_lead_type,
                'auto_reply_email_template_id' => $this->resource->auto_reply_email_template_id,
                'auto_reply_ask_phone_email_template_id' => $this->resource->auto_reply_ask_phone_email_template_id,
            ];
        } else {
            $response = $this->resource->attributesToArray();
        }

        $response = $this->loadTags($response);
        $response = $this->loadUsersToAssign($response);
        $response = $this->loadAutoReplyEmailTemplate($response);
        $response = $this->loadAutoReplyAskPhoneEmailTemplate($response);

        // $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadAutoReplyAskPhoneEmailTemplate(array $response)
    {
        if (!$this->resource->auto_reply_ask_phone_email_template_id) {
            $response['askPhoneEmailTemplate'] = null;
            return $response;
        }

        if (!$this->resource->relationLoaded('askPhoneEmailTemplate')) {
            $this->resource->load([
                'askPhoneEmailTemplate' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }

        $visibleFields = ['id', 'title'];
        $rs = new EmailTemplateResource($this->resource->askPhoneEmailTemplate);
        $rs->setVisibleFields($visibleFields);
        $response['askPhoneEmailTemplate'] = $rs;
        return $response;
    }


    private function loadAutoReplyEmailTemplate(array $response)
    {
        if (!$this->resource->auto_reply_email_template_id) {
            $response['autoReplyEmailTemplate'] = null;
            return $response;
        }

        if (!$this->resource->relationLoaded('autoReplyEmailTemplate')) {
            $this->resource->load([
                'autoReplyEmailTemplate' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }

        $visibleFields = ['id', 'title'];
        $rs = new EmailTemplateResource($this->resource->autoReplyEmailTemplate);
        $rs->setVisibleFields($visibleFields);
        $response['autoReplyEmailTemplate'] = $rs;
        return $response;
    }


    private function loadUsersToAssign(array $response)
    {
        if (empty($this->resource->assign_user_ids)) {
            $response['usersToAssign'] = [];
            return $response;
        }

        $users = User::whereIn('id', $this->resource->assign_user_ids)
            ->where('enabled', true)
            ->get()
        ;

        $rs = new UserResourceCollection($users);
        $rs->setVisibleFields(['id', 'name', 'last_name']);
        $response['usersToAssign'] = $rs;
        return $response;
    }


    private function loadTags(array $response)
    {
        $tags = $this->resource->getTagsToAddAttribute(['withTrashed' => true]);
        if (empty($tags)) {
            $response['tags'] = null;
            return $response;
        }

        $response['tags'] = $tags;
        return $response;
    }
}
