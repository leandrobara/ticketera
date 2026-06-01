<?php

namespace App\Http\Requests\UserCustomFilter;

use App\DTO\UserCustomFilter\UserCustomFilterDTO;
use App\Http\Requests\APIBaseRequest;
use App\Models\UserCustomFilter;
use App\Rules\InUserCustomFilterReturnFields;

class UpdateUserCustomFilterRequest extends APIBaseRequest
{
    private $client;

    private $user;

    public function rules()
    {
        return [
            'name' => ['sometimes', 'string'],
            'filters' => ['required', 'json'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InUserCustomFilterReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $this->client = request()->input('client');
                $this->user = request()->input('user');
                if (request()->userCustomFilter->client_id != $this->client->id) {
                    $validator->errors()->add('client_id', 'filter_client_does_not_match_with_authenticated_client');
                    return false;
                }

                $filter = UserCustomFilter::where([
                    'client_id' => $this->client->id,
                    'user_id' => $this->user->id,
                    'name' => request()->input('name')
                ])->first();

                if ($filter && request()->userCustomFilter->id != $filter->id) {
                    $validator->errors()->add('name', 'filter_name_already_exists');

                    return false;
                }

                if (request()->userCustomFilter->user_id != $this->user->id) {
                    $validator->errors()->add('user_id', 'filter_user_does_not_match_with_authenticated_client');
                    return false;
                }
            }
        });
    }

    public function validatedDTO()
    {
        $validated = parent::validated();

        $validated['client'] = $this->client;
        $validated['user'] = $this->user;

        return UserCustomFilterDTO::build($validated);
    }
}
