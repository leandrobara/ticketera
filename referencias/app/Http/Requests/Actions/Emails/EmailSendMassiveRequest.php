<?php

namespace App\Http\Requests\Actions\Emails;

use App\Models\Attachment;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;
use App\DTO\EmailMassiveSendParametersDTO;
use App\Services\API\LeadContactEmailService;


class EmailSendMassiveRequest extends APIBaseRequest
{

    protected $attachments = [];
    protected $leadContactEmails = [];

    public function rules()
    {
        return [
            'body' => 'bail|required|string',
            'subject' => 'bail|required|string',
            'isProposal' => 'bail|sometimes|boolean',
            'attachmentIds' => 'bail|sometimes|array',
            'attachmentIds.*' => 'bail|sometimes|integer',
            'lead_contact_email_id' => ['required', 'array'],
            'lead_contact_email_id.*' => ['required', 'integer'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->client;

                if ($client->clientSettings->email_sending_blocked) {
                    $validator->errors()->add('client_id', 'email_sending_blocked');
                    return false;
                }

                $leadContactEmailIds = collect(request()->input('lead_contact_email_id'));
                $service = resolve(LeadContactEmailService::class);

                $with = [
                    'lead' => function ($q) {
                        $q->select(['id', 'company']);
                    },
                    'leadContact' => function ($q) {
                        $q->select(['id', 'name', 'last_name']);
                    },
                ];
                $opts = ['with' => $with];
                $this->leadContactEmails = $service->findByClientAndIds($client, $leadContactEmailIds, $opts);
                if ($this->leadContactEmails->isEmpty()) {
                    $validator->errors()->add('lead_contact_email_id', 'none_lead_contact_email_exists');
                    return false;
                }

                if ($this->leadContactEmails->count() != $leadContactEmailIds->count()) {
                    $validator->errors()->add('lead_contact_email_id', 'some_lead_contact_email_does_not_exist');
                    return false;
                }

                $attachmentIds = collect(request()->input('attachmentIds', []));
                $attachments = Attachment::where('client_id', $client->id)->whereIn('id', $attachmentIds)->get();
                if ($attachmentIds->count() != $attachments->count()) {
                    $validator->errors()->add('attachmentIds', 'some_attachments_does_not_belong_to_client');
                    return false;
                }
                $this->attachments = $attachments;
            }
        });
    }


    
    public function validatedDTO(): EmailMassiveSendParametersDTO
    {
        $val = parent::validated();
        $val['attachments'] = $this->attachments;
        $val['leadContactEmails'] = $this->leadContactEmails;
        $dto = EmailMassiveSendParametersDTO::build($val);
        return $dto;
    }

}
