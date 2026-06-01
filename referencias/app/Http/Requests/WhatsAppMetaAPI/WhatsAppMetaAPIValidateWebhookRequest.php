<?php

namespace App\Http\Requests\WhatsAppMetaAPI;

use Illuminate\Support\Facades\Log;
use App\Http\Requests\APIBaseRequest;


class WhatsAppMetaAPIValidateWebhookRequest extends APIBaseRequest
{

    protected function prepareForValidation()
    {
        // Ejecuta antes de la validación
        $log = Log::channel('WhatsAppMetaAPIControllerInfo');
        $log->info('==== VALIDATE WEBHOOK ==== ');
        $log->info('- QUERY: ');
        $log->info($this->query());
        $log->info('- URL: ' . $this->fullUrl());
        $log->info('- ALL INPUT: ');
        $log->info($this->all());
        $log->info('- METHOD: ' . $this->method());
        $log->info('-------------------------------------------------' . PHP_EOL);
    }


    public function rules()
    {
        return [
            'hub_mode' => ['required', 'string'],
            'hub_challenge' => ['required', 'string'],
            'hub_verify_token' => ['required', 'string'],
        ];
    }


    public function validated($key = null, $default = null)
    {
        $val['hub_mode'] = request()->input('hub_mode');
        $val['hub_challenge'] = request()->input('hub_challenge');
        $val['hub_verify_token'] = request()->input('hub_verify_token');
        return $val;
    }

}
