<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;


class InLeadSaleReturnFields implements Rule
{

    private $allowedFields = [
        'id',
        'lead',
        'user',
        'client',
        'client',
        'amount',
        'lead_id',
        'user_id',
        'client_id',
        'sale_date',
        'description',
        
    ];

    private $customErrVal;


    public function passes($attribute, $value)
    {
        $ok = in_array($value, $this->allowedFields);
        if (!$ok) {
            $this->customErrVal = $value;
        }
        return $ok;
    }


    public function message()
    {
        return 'The field "' . $this->customErrVal . '" is not a LeadSale field.';
    }

}
