<?php

namespace App\Http\Requests;


class SaveEmailDraftRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'body' => 'bail|nullable|required_without:subject|string',
            'subject' => 'bail|nullable|required_without:body|string',
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (request()->lead->client_id != request()->input('client')->id) {
                $validator->errors()->add(
                    'client_id',
                    'lead_client_does_not_match_with_authenticated_client'
                );
            }
        });
    }


    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        if (!($validated['body'] ?? false)) {
            $validated['body'] = null;
        }
        if (!($validated['subject'] ?? false)) {
            $validated['subject'] = null;
        }
        return $validated;
    }

}
