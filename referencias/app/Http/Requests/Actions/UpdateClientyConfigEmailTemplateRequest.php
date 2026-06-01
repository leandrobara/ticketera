<?php

namespace App\Http\Requests\Actions;

use App\Http\Requests\APIBaseRequest;
use App\Rules\InClientyConfigEmailTemplateReturnFields;


class UpdateClientyConfigEmailTemplateRequest extends APIBaseRequest
{

    protected $attachments = [];


    public function rules()
    {
        return [
            'body' => ['required', 'string'],
            'title' => ['sometimes', 'string'],
            'subject' => ['required', 'string'],
            'is_proposal' => ['sometimes', 'boolean'],
            'business_area_id' => ['sometimes', 'nullable', 'int'],
            'business_area_child_id' => ['sometimes', 'nullable', 'int'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InClientyConfigEmailTemplateReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $clientyClientId = (int) config('app.clienty.client_id');
                $client = request()->input('client');
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;

                $businessAreaId = request()->input('business_area_id');
                $businessAreaChildId = request()->input('business_area_child_id');

                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }
                if (!$isSuperUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }
                if (!$businessAreaId && $businessAreaChildId) {
                    $validator->errors()->add('business_area_child', 'business_area_child_must_be_null');
                    return false;
                }
            });
        }
    }


    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        return $validated;
    }

}
