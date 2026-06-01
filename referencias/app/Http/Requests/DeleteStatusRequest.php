<?php

namespace App\Http\Requests;

use App\Models\Status;
use App\Rules\InStatusReturnFields;
use App\Repositories\StatusRepository;


class DeleteStatusRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InStatusReturnFields()]
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $status = request()->status;
            $client = request()->input('client');

            if ($status->client_id != $client->id) {
                $validator->errors()->add('client_id', 'status_client_does_not_match_with_authenticated_client');
                return false;
            }

            if ($status->automationsEmailSend->count() > 0) {
                $validator->errors()->add('status', 'status_has_associated_automation_email_send');
                return false;
            }

            if ($status->wAutomationsSequence->count() > 0) {
                $validator->errors()->add('status', 'status_has_associated_automation_wautomations_sequence');
                return false;
            }

            if ($status->automationsProposalInteractionRule->count() > 0) {
                $validator->errors()->add('status', 'status_has_associated_automation_proposal_interaction_rule');
                return false;
            }

            if ($status->automationsProposalModifyLeadAfterSendRule->count() > 0) {
                $validator->errors()->add(
                    'status', 'status_has_associated_automation_proposal_modify_lead_after_send_rule'
                );
                return false;
            }

            if ($status->leads()->count() > 0) {
                $validator->errors()->add('status', 'status_has_associated_leads');
                return false;
            }

            $clientStatusCount = Status::where('client_id', $client->id)->count();
            if ($clientStatusCount <= 1) {
                $validator->errors()->add('status', 'status_can_not_be_empty');
                return false;
            }

            $statusName = strtolower(trim($status->name));
            $statusNamesDisabledToDelete = config('app.status_disabled_to_delete');
            if (in_array($statusName, $statusNamesDisabledToDelete)) {
                $validator->errors()->add('name', 'status_disabled_to_delete');
                return false;
            }
        });
    }

}