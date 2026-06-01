<?php

namespace App\Http\Requests\WhatsAppMetaAPI;

use Illuminate\Support\Facades\Log;
use App\Http\Requests\APIBaseRequest;


class WhatsAppMetaAPIHandleNewWebhookRequest extends APIBaseRequest
{

    protected function prepareForValidation()
    {
        // Ejecuta antes de la validación
        $log = Log::channel('WhatsAppMetaAPIControllerInfo');
        $log->info('==== NEW WEBHOOK ==== ');
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
        return [];
    }


    public function isStatusChangeMessage(): bool
    {
        $metaPayload = $this->post();
        $isStatusChangeMsg = isset($metaPayload['entry'][0]['changes'][0]['value']['statuses']);
        return $isStatusChangeMsg;
    }

    public function isIncomingMessage(): bool
    {
        $metaPayload = $this->post();
        $isIncomingMessage =
            isset($metaPayload['entry'][0]['changes'][0]['field']) &&
            $metaPayload['entry'][0]['changes'][0]['field'] === 'messages' &&
            isset($metaPayload['entry'][0]['changes'][0]['value']['messages'])
        ;
        return $isIncomingMessage;
    }


    public function isOutgoingEchoMessage(): bool
    {
        $metaPayload = $this->post();
        return isset($metaPayload['entry'][0]['changes'][0]['field']) &&
            $metaPayload['entry'][0]['changes'][0]['field'] === 'smb_message_echoes' &&
            isset($metaPayload['entry'][0]['changes'][0]['value']['message_echoes'])
        ;
    }

}
