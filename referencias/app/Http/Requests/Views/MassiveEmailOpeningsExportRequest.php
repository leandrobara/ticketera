<?php

namespace App\Http\Requests\Views;

use App\Models\Email;
use Illuminate\Support\Collection;
use App\Http\Requests\APIBaseRequest;


class MassiveEmailOpeningsExportRequest extends APIBaseRequest
{

    private $client;
    private $emailExternalMassiveId;


    public function rules()
    {
        return [
            'filters' => ['required', 'array'],
            'filters.subject' => ['required', 'string'],
            'filters.external_massive_id' => ['required', 'string'],
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->failed()) {
                $this->client = request()->input('client');
                $this->emailExternalMassiveId = request()->filters['external_massive_id'];

                $emailFromOtherClient = Email::where('external_massive_id', $this->emailExternalMassiveId)
                    ->where('client_id', '!=', $this->client->id)
                    ->first()
                ;
                if ($emailFromOtherClient) {
                    $validator->errors()->add('client_id', 'error_client_id');
                    return false;
                }
            }
        });
    }


    public function validated($key = null, $default = null)
    {
        $val = parent::validated();
        $val['limit'] = 999999999;
        $val['filters']['email_id'] = $this->getEmailIds();
        $val['filters']['event'] = 'open';
        $val['with'] = [
            'email' => function ($q) {
                $q->withTrashed();
            },
            'email.lead' => function ($q) {
                $q->withTrashed();
            },
            'email.leadContactEmail' => function ($q) {
                $q->withTrashed();
            },
            'email.leadContactEmail.leadContact' => function ($q) {
                $q->withTrashed();
            },
        ];

        if ($val['filters']['external_massive_id'] ?? false) {
            unset($val['filters']['external_massive_id']);
        }

        $val['subject'] = $val['filters']['subject'];
        if ($val['filters']['subject'] ?? false) {
            unset($val['filters']['subject']);
        }

        return $val;
    }


    public function getSubject()
    {
        $val = parent::validated();
        $val['subject'] = $val['filters']['subject'];
        if ($val['filters']['subject'] ?? false) {
            unset($val['filters']['subject']);
        }
        return $val['subject'];
    }


    private function getEmailIds()
    {
        $emailIds = Email::where('external_massive_id', $this->emailExternalMassiveId)
            ->where('client_id', $this->client->id)
            ->get()
            ->pluck('id')
            ->toArray()
        ;

        return $emailIds;
    }

}
