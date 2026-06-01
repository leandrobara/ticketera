<?php

namespace App\Http\Requests\UserCustomFilter;

use App\DTO\UserCustomFilter\UserCustomFilterDTO;
use App\Http\Requests\APIBaseRequest;
use App\Models\UserCustomFilter;
use App\Rules\InUserCustomFilterReturnFields;

class CreateUserCustomFilterRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'name' => ['required', 'string'],
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
                $filter = UserCustomFilter::where([
                    'client_id' => $this->client->id,
                    'user_id' => $this->user->id,
                    'name' => request()->input('name')
                ])->first();

                if ($filter) {
                    $validator->errors()->add('name', 'filter_name_already_exists');

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
