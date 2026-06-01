<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Str;
use App\Http\Requests\WapSalesAgentTestRequest;
use App\Jobs\WhatsAppEvents\WapSalesAgentAnswerIncomingMessageJob;


class WapSalesAgentTestController extends BaseAPIController
{

    public function sendMessage(WapSalesAgentTestRequest $request)
    {
        $message = $request->input('message');
        $client = $request->input('client');
        $user = $request->input('user');
        $testPhoneNumber = 'test_' . $client->id;

        $payload = [
            '_test' => true,
            '_test_client_id' => $client->id,
            '_test_user_id' => $user->id,
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'metadata' => ['phone_number_id' => $testPhoneNumber],
                                'messages' => [
                                    [
                                        'from' => $testPhoneNumber,
                                        'id' => 'wamid.' . Str::random(40),
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => $message],
                                    ]
                                ],
                            ],
                        ]
                    ],
                ]
            ],
        ];

        $path = 'logs/WapSalesAgentAnswerIncomingMessageJobInfo-' . now()->format('Y-m-d') . '.log';
        $logPath = storage_path($path);
        $logSizeBefore = file_exists($logPath) ? filesize($logPath) : 0;

        WapSalesAgentAnswerIncomingMessageJob::dispatchSync($payload);

        $responses = [];
        if (file_exists($logPath) && filesize($logPath) > $logSizeBefore) {
            $newContent = file_get_contents($logPath, false, null, $logSizeBefore);
            preg_match_all("/Response sent: '([^']*(?:\\\\'[^']*)*)'/", $newContent, $matches);
            $responses = $matches[1] ?? [];
        }

        return $this->getSuccessResponse(['responses' => $responses]);
    }

}
