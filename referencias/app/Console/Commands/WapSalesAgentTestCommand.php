<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Jobs\WhatsAppEvents\WapSalesAgentAnswerIncomingMessageJob;


class WapSalesAgentTestCommand extends Command
{

    protected $signature = 'wap-sales-agent:test';
    protected $description = 'Conversación interactiva con WapSalesAgent (escribí mensajes, ves respuestas)';


    public function handle()
    {
        $this->info('WapSalesAgent Test - Escribí mensajes para conversar. "salir" para terminar.');
        $this->newLine();

        while (true) {
            $message = $this->ask('Tú');
            if ($message === null || strtolower(trim($message)) === 'salir') {
                $this->info('Chau.');
                return 0;
            }
            if (empty(trim($message))) {
                continue;
            }

            $payload = [
                'entry' => [
                    [
                        'changes' => [
                            [
                                'field' => 'messages',
                                'value' => [
                                    'metadata' => ['phone_number_id' => '247733821746167'],
                                    'messages' => [
                                        [
                                            'from' => '5491159711575',
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

            $responseText = !empty($responses) ? implode("\n", $responses) : '(sin respuesta)';
            $this->line('<fg=green>Bot:</> ' . $responseText);
            $this->newLine();
        }
    }

}
