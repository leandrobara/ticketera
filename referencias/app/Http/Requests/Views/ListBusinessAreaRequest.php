<?php

namespace App\Http\Requests\Views;

use DateTime;
use DateTimeZone;
use Illuminate\Validation\Rule;
use App\Http\Requests\APIBaseRequest;
use App\Rules\IsRequiredIntegerOrArray;
use App\Rules\IsRequiredNullableIntegerOrArray;


class ListBusinessAreaRequest extends APIBaseRequest
{

    public function rules()
    {
        return [];
    }


    public function validated($key = null, $default = null)
    {
        //To eager load relations
        $val['with'] = [
            'businessAreaChildren',
        ];
        if (!isset($val['sort'])) {
            $val['sort'] = 'name';
        }
        return $val;
    }

}
