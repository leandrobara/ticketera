<?php

namespace App\Http\Requests\Integration;

use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;
use App\Rules\IsRequiredNullableIntegerOrArray;


class ListLeadForMakeRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'page' => ['sometimes', 'nullable', 'int'],
            'limit' => ['sometimes', 'nullable', 'int'],
            'sort' => ['sometimes', 'nullable', Rule::in('date_asc', 'date_desc')],
            'filters' => ['sometimes', 'array'],
            'filters.search' => ['sometimes', 'nullable', 'string'],
            'filters.id' => ['sometimes', 'array', 'max:30'],
            'filters.id.*' => ['required', 'integer'],
            'filters.user_id' => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
            'filters.quality' => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
            'filters.status_id' => ['sometimes', 'nullable', new IsRequiredIntegerOrArray()],
            'filters.tag_id' => ['sometimes', 'nullable', new IsRequiredNullableIntegerOrArray()],
            'filters.landing_id' => ['sometimes', 'nullable', new IsRequiredNullableIntegerOrArray()],
            'filters.acquisition_channel_id' => ['sometimes', 'nullable', new IsRequiredNullableIntegerOrArray()],
        ];
    }


    public function withValidator($validator)
    {
        if (!$validator->failed()) {
            $validator->after(function ($validator) {
                $filters = request()->input('filters');
                $searchTerm = $filters['search'] ?? null;
                if ($searchTerm && strlen($searchTerm) > 50) {
                    $validator->errors()->add('filters', 'filters_search_max_length_exceeded');
                    return false;
                }
            });
        }
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();

        //To eager load relations
        $val['with'] = [
            'tags',
            'user',
            'notes',
            'status',
            'client',
            'landing',
            'leadContacts',
            'acquisitionChannel',
            'leadCustomFieldsValues',
            'client.leadsCustomFields',
            'leadContacts.leadContactEmails',
            'leadContacts.leadContactPhones',
        ];

        return $val;
    }
}
