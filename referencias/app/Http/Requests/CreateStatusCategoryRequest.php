<?php

namespace App\Http\Requests;

use App\Models\StatusCategory;
use Illuminate\Validation\Rule;


class CreateStatusCategoryRequest extends APIBaseRequest
{
    
    public function rules()
    {
        return [
            'name' => ['required', 'string'],
            'is_irrelevant' => ['sometimes', 'bool'],
            'sale_probability' => ['sometimes', 'int'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $clientId = request()->client->id;
                $hash = StatusCategory::buildHash(request()->input('name'));
                $existentStatusCateg = StatusCategory::where('client_id', $clientId)->where('hash', $hash)->first();
                if ($existentStatusCateg) {
                    $validator->errors()->add('name', 'status_category_already_exists');
                    return false;
                }
            }
        });
    }

}
