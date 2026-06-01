<?php

namespace App\Http\Requests;


class WapSalesAgentTestRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'message' => ['required', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $isSuperUser = request()->jwtPayload['is_super_user'] ?? false;
                if (!$isSuperUser) {
                    $validator->errors()->add('user_type', 'user_must_be_superuser');
                    return false;
                }
            }
        });
    }

}
