<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Rules\InUserNotificationReturnFields;

class CreateUserNotificationRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'notification_type' => [
                'string',
                'required',
                Rule::in(
                    [
                        'unsubscribe',
                        'error_report',
                        'need_callback',
                        'need_more_users',
                        'need_more_email_sending_quota',
                    ]
                )
            ],
            'unsubscribe_reason' => [
                'string',
                'nullable',
                'sometimes',
                Rule::in(
                    [
                        'other',
                        'not_suiting_my_needs',
                        'no_time_to_implement',
                        'temporary_unsubscribe',
                        'inconvenient_loading_leads',
                        'inconvenient_with_landing_pages',
                        'inconvenient_linking_other_system',
                    ]
                )
            ],
            'comments' => ['string', 'required'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InUserNotificationReturnFields()],
        ];
    }


    public function validateRequest()
    {
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        return $validated;
    }

}