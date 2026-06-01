<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Rules\InStatusReturnFields;
use App\Services\API\StatusService;
use App\Services\API\StatusCategoryService;


class UpdateStatusRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'name' => ['sometimes', 'string'],
            'text_color' => ['sometimes', 'string'],
            'background_color' => ['sometimes', 'string'] ,
            'sale_probability' => ['sometimes', 'integer'],
            'status_category_id' => ['sometimes', 'integer'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InStatusReturnFields()]
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                if (request()->status->client_id != $client->id) {
                    $validator->errors()->add('client_id', 'status_client_does_not_match_with_authenticated_client');

                    return false;
                }

                if (request()->input('name')) {
                    $name = request()->input('name');
                    $existentStatus = resolve(StatusService::class)->findOneByClientAndName($client, $name);
                    if ($existentStatus && $existentStatus->id != request()->status->id) {
                        $validator->errors()->add('name', 'status_already_exists');
                        return false;
                    }
                }
                
                $statusName = strtolower(trim(request()->status->name));
                $newStatusName = strtolower(trim(request()->input('name')));
                $statusNameIsChanging = $statusName != $newStatusName;
                $statusNamesDisabledToEditName = config('app.status_disabled_to_edit_name');
                if ($statusNameIsChanging && in_array($statusName, $statusNamesDisabledToEditName)) {
                    $validator->errors()->add('name', 'status_disabled_to_edit_name');
                    return false;
                }

                $statusCategoryId = request()->input('status_category_id');
                if ($statusCategoryId) {
                    $statusCategory = resolve(StatusCategoryService::class)->find($statusCategoryId);
                    if (!$statusCategory) {
                        $validator->errors()->add('status_category_id', 'status_category_does_not_exists');
                        return false;
                    }
                    if ($statusCategory->client_id != $client->id) {
                        $validator->errors()->add(
                            'status_category_id', 'status_category_client_does_not_match_with_authenticated_client'
                        );
                        return false;
                    }
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
