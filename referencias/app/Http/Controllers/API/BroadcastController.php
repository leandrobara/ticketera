<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;


class BroadcastController extends BaseAPIController
{

    public function auth(Request $request)
    {
        $socketId = $request->input('socket_id');
        $channelName = $request->input('channel_name');

        $arr = explode('.', $channelName);
        $clientId = intval(array_pop($arr));
        $client = $request->client;
        if (!$client || !$clientId || $clientId !== $client->id) {
            return [];
        }

        // Seleccionar credenciales de Pusher según el canal
        $pusherConfig = $this->getPusherConfigForChannel($channelName);

        $stringToSign = $socketId . ':' . $channelName;
        $signature = hash_hmac('sha256', $stringToSign, $pusherConfig['secret']);

        return ['auth' => "{$pusherConfig['key']}:{$signature}"];
    }


    /**
     * Cada app de Pusher tiene sus propias credenciales. El canal determina cuál usar.
     */
    private function getPusherConfigForChannel(string $channelName): array
    {
        if (str_contains($channelName, 'whatsapp-conversations.')) {
            return [
                'key' => config('broadcasting.connections.pusher_whatsapp_conversations.key'),
                'secret' => config('broadcasting.connections.pusher_whatsapp_conversations.secret'),
            ];
        }
        return [
            'key' => config('broadcasting.connections.pusher.key'),
            'secret' => config('broadcasting.connections.pusher.secret'),
        ];
    }

}
