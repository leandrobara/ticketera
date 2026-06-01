<?php

namespace App\Http\Requests;

use App\Models\Tag;
use App\Rules\InTagReturnFields;


class DeleteTagRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InTagReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $tag = request()->tag;
            $client = request()->input('client');

            if ($tag->client_id != request()->input('client')->id) {
                $validator->errors()->add('client_id', 'tag_client_does_not_match_with_authenticated_client');
            }

            if ($tag->automationsEmailSend->count() > 0) {
                $validator->errors()->add('tag', 'tag_has_associated_automation_email_send');
                return false;
            }

            if ($tag->wAutomationsSequence->count() > 0) {
                $validator->errors()->add('tag', 'tag_has_associated_automation_wautomations_sequence');
                return false;
            }
        });
    }

}
