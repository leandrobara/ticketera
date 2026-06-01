<?php

namespace App\Http\Requests;

use App\Models\TagCategory;
use Illuminate\Validation\Rule;
use App\Rules\InTagCategoryReturnFields;


class CreateTagCategoryRequest extends APIBaseRequest
{
    public function rules()
    {
        return [
            'name' => ['required', 'string'],
            'category' => ['sometimes', Rule::in(['new', 'sale', 'in_process', 'without_sale'])],
            'text_color' => ['sometimes', 'string'],
            'background_color' => ['sometimes', 'string'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['sometimes', new InTagCategoryReturnFields()],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $hash = TagCategory::buildHash(request()->input('name'));
                $channel = TagCategory::where('client_id', request()->client->id)->where('hash', $hash)->first();
                if ($channel) {
                    $validator->errors()->add(
                        'name',
                        'tag_category_already_exists'
                    );
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
