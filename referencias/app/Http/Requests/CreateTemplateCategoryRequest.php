<?php

namespace App\Http\Requests;

use App\Models\TemplateCategory;
use Illuminate\Validation\Rule;


class CreateTemplateCategoryRequest extends APIBaseRequest
{
    
    public function rules()
    {
        return [
            'name' => ['required', 'string'],
            'text_color' => ['sometimes', 'string'],
            'background_color' => ['sometimes', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $clientId = request()->client->id;
                $hash = TemplateCategory::buildHash(request()->input('name'));
                $templateCategory = TemplateCategory::where('client_id', $clientId)->where('hash', $hash)->first();
                if ($templateCategory) {
                    $validator->errors()->add('name', 'template_category_already_exists');
                    return false;
                }
            }
        });
    }

}
