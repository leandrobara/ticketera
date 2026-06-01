<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Rules\InStatusReturnFields;
use App\Repositories\StatusRepository;
use App\Services\API\StatusCategoryService;


class CreateStatusRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'name' => ['required', 'string'],
            'text_color' => ['sometimes', 'string'],
            'sale_probability' => ['required', 'integer'],
            'background_color' => ['sometimes', 'string'],
            'status_category_id' => ['required', 'integer'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InStatusReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $name = request()->input('name');
                $client = request()->input('client');
                $statusCategoryId = request()->input('status_category_id');

                $existentStatus = resolve(StatusRepository::class)->findOneByClientAndName($client, $name);
                if ($existentStatus) {
                    $validator->errors()->add('name', 'status_already_exists');
                    return false;
                }

                $statusCategory = resolve(StatusCategoryService::class)->find($statusCategoryId);
                if (!$statusCategory) {
                    $validator->errors()->add('status_category_id', 'status_category_does_not_exists');
                    return false;
                }
                if ($statusCategory->client_id != $client->id) {
                    $validator->errors()->add(
                        'client_id', 'status_category_client_does_not_match_with_authenticated_client'
                    );
                    return false;
                }
            }
        });
    }

    public function validatedAttributes()
    {
        $validated = parent::validated();
        if ($validated['fields'] ?? false) {
            unset($validated['fields']);
        }
        return $validated;
    }
}
