<?php

namespace App\Http\Requests\Views;

use App\Models\Email;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class MassiveEmailExportRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'filters' => ['required', 'array'],
            'filters.external_massive_id' => ['required', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $client = request()->input('client');
                $emailExternalMassiveId = request()->filters['external_massive_id'];

                $emailFromOtherClienty = Email::where('external_massive_id', $emailExternalMassiveId)
                    ->where('client_id', '!=', $client->id)
                    ->first()
                ;
                if ($emailFromOtherClienty) {
                    $validator->errors()->add('client_id', 'error_client_id');
                    return false;
                }
            }
        });
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        $val['with'] = [
            'lead.leadCustomFieldsValues',
            'client.leadsCustomFields',
            'lead' => function ($q) {
                $q->withTrashed();
            },
            'client' => function ($q) {
                $q->withTrashed();
            },
            'leadContactEmail' => function ($q) {
                $q->withTrashed();
            },
            'leadContactEmail.leadContact' => function ($q) {
                $q->withTrashed();
            },
        ];
        return $val;
    }
}
