<?php

namespace App\Http\Requests\Views;

use App\Http\Requests\APIBaseRequest;


class QuickSearchLeadRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'limit' => ['sometimes', 'int'],
            'search' => ['required', 'string'],
            'filters' => ['sometimes', 'array', 'nullable'],
            'filters.user_id' => ['sometimes', 'integer', 'nullable'],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        $limit = intval($validated['limit'] ?? 20);
        $limit = ($limit <= 100) ? $limit : 50;
        if ($validated['limit'] ?? false) {
            unset($validated['fields']);
        }
        $validated['limit'] = $limit;
        if ($validated['filters']['user_id'] ?? null) {
            $validated['filters']['user_id'] = (int) $validated['filters']['user_id'];
        }
        
        $validated['filters']['search'] = substr($validated['search'], 0, 80);
        unset($validated['search']);
        return $validated;
    }

}
