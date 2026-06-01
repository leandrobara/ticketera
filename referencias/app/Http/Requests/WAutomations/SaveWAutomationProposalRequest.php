<?php

namespace App\Http\Requests\WAutomations;

use DateTime;
use DateTimeZone;
use App\Models\Tag;
use App\Models\Status;
use App\Models\WhatsAppTemplate;
use App\Http\Requests\APIBaseRequest;
use App\DTO\WAutomations\WAutomationProposalDTO;
use App\Rules\InWAutomationProposalReturnFields;


class SaveWAutomationProposalRequest extends APIBaseRequest
{

    // wautomation proposal resend rule
    private $assignStatus = null;
    private $cancellingTags = null;
    private $cancellingStatus = null;
    private $sendWhatsAppTemplate = null;


    public function rules()
    {
        return [
            // wautomation proposal resend rule
            'enabled' => ['required', 'boolean'],
            'resendRule.send_whatsapp_template_id' => ['sometimes', 'integer'],
            'resendRule.enabled' => ['sometimes', 'boolean'],
            'resendRule.cancelling_enabled' => ['sometimes', 'boolean'],
            'resendRule.cancelling_tags_ids' => ['sometimes', 'array'],
            'resendRule.cancelling_status_ids' => ['sometimes', 'array'],
            'resendRule.do_not_send_weekends' => ['sometimes', 'boolean'],
            'resendRule.add_original_attachments' => ['sometimes', 'boolean'],
            'resendRule.cancel_if_proposal_was_already_sent' => ['sometimes', 'boolean'],
            'resendRule.send_delay_days' => ['sometimes', 'integer'],
            'resendRule.send_hour' => ['sometimes', 'string'],
            // wautomation after send proposal
            'modifyLeadAfterSendRule.add_sent_proposal_tag' => ['sometimes', 'boolean', 'nullable'],
            'modifyLeadAfterSendRule.assign_status_id' => ['sometimes', 'integer', 'nullable'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InWAutomationProposalReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $cancellingTagIds = request()->input('resendRule.cancelling_tags_ids');
                if ($cancellingTagIds) {
                    $tags = Tag::where('client_id', $client->id)->whereIn('id', $cancellingTagIds)->get();
                    if ($tags->count() != count($cancellingTagIds)) {
                        $validator->errors()->add('cancelling_tags_ids', 'not_all_cancelling_tags_exists');
                        return false;
                    }
                    $this->cancellingTags =  $tags;
                }

                $cancellingStatusIds = request()->input('resendRule.cancelling_status_ids');
                if ($cancellingStatusIds) {
                    $status = Status::where('client_id', $client->id)->whereIn('id', $cancellingStatusIds)->get();
                    if ($status->count() != count($cancellingStatusIds)) {
                        $validator->errors()->add('cancelling_status_ids', 'not_all_cancelling_status_exists');
                        return false;
                    }
                    $this->cancellingStatus = $status;
                }

                $whatsAppTemplateId = request()->input('resendRule.send_whatsapp_template_id');
                if ($whatsAppTemplateId) {
                    $this->sendWhatsAppTemplate = WhatsAppTemplate::where([
                        'client_id' => $client->id, 'id' => $whatsAppTemplateId
                    ])->first();

                    if (!$this->sendWhatsAppTemplate) {
                        $validator->errors()->add(
                            'send_whatsapp_template_id', 'send_whatsapp_template_id_does_not_exists'
                        );
                    }
                }

                $statusId = request()->input('modifyLeadAfterSendRule.assign_status_id');
                if ($statusId) {
                    $status = Status::where('client_id', $client->id)->where('id', $statusId)->first();
                    if (!$status) {
                        $validator->errors()->add('assign_status_id', 'assign_status_does_not_exists');
                        return false;
                    }
                    $this->assignStatus = $status;
                }
            }
        });
    }


    public function validatedDTO(): WAutomationProposalDTO
    {
        $val = parent::validated();

        if ($val['resendRule'] ?? null) {
            $val['resendRule']['cancellingTags'] = $this->cancellingTags;
            $val['resendRule']['cancellingStatus'] = $this->cancellingStatus;
            $val['resendRule']['sendWhatsAppTemplate'] = $this->sendWhatsAppTemplate;

            $client = request()->input('client');
            $tz = $client->timezone;
            $sendHourArr = explode(':', $val['resendRule']['send_hour']);
            $hour = (int) $sendHourArr[0];
            $minutes = (int) $sendHourArr[1];
            // Set date (with hour and minute) with Client TZ
            $date = (new DateTime())->setTimezone(new DateTimeZone($tz))->setTime($hour, $minutes, 0);
            // Convert client's TZ to UTC0
            $date->setTimezone(new DateTimeZone('UTC'));
            $val['resendRule']['send_hour'] = $date->format('H:i');
        }

        if ($this->assignStatus) {
            $val['modifyLeadAfterSendRule']['assignStatus'] = $this->assignStatus;
        }
        $dto = WAutomationProposalDTO::build($val);
        return $dto;
    }

}
