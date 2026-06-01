<?php

namespace App\Http\Requests;

use App\Models\TagCategory;
use Illuminate\Validation\Rule;
use App\Rules\InTagReturnFields;
use App\Services\API\TagService;


class CreateTagRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'name' => ['required', 'string'],
            'text_color' => ['sometimes', 'string'],
            'background_color' => ['sometimes', 'string'],
            'tag_category_id' => ['sometimes', 'nullable', 'int'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InTagReturnFields()],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $name = request()->input('name');
                $client = request()->input('client');
                $existentTag = resolve(TagService::class)->findOneByClientAndName($client, $name);
                if ($existentTag) {
                    $validator->errors()->add('name', 'tag_already_exists');
                    return false;
                }

                $tagCategoryId = request()->input('tag_category_id');
                if ($tagCategoryId) {
                    $tagCategory = TagCategory::find($tagCategoryId);
                    if (!$tagCategory) {
                        $validator->errors()->add('tag_category_id', 'tag_category_does_not_exists');
                        return false;
                    }
                    if ($tagCategory->client_id != $client->id) {
                        $validator->errors()->add(
                            'tag_category_id', 'tag_category_client_does_not_match_with_authenticated_client'
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
