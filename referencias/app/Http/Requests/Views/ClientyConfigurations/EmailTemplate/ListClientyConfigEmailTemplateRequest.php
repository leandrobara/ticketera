<?php

namespace App\Http\Requests\Views\ClientyConfigurations\EmailTemplate;

use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;
use App\Models\ClientyConfigEmailTemplate;
use App\Rules\InClientyConfigEmailTemplateReturnFields;


class ListClientyConfigEmailTemplateRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'order' => ['sometimes', Rule::in(['date_asc', 'date_desc'])],
            'filters' => ['sometimes', 'array'],
            'filters.business_area_id' => ['sometimes', 'nullable', 'int'],
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

                if ($client->id != $clientyClientId) {
                    $validator->errors()->add('client_id', 'current_client_is_not_clienty');
                    return false;
                }
                
                if (!$isSuperUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }
            });
        }
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        if (!isset($val['order'])) {
            $val['order'] = 'title';
        }
        return $val;
    }
}
