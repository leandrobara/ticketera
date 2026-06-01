<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;
use App\Models\WhatsAppSendingMessage;
use App\Models\MongoDB\WhatsAppMetaAPI\WhatsAppConversationMessage;


class WhatsAppConversationUpdateStatusFromSentMessagesCommand extends Command
{

    protected $signature = 'whatsapp-conversation-messages:update-status-from-sent-messages ' .
        '{--client-id=} {--min-client-id=} {--chunk=100}'
    ;
    protected $description = 'Update WhatsAppConversationMessages status and error from WhatsAppSendingMessages';


    public function handle()
    {
        $clientId = $this->option('client-id') ? (int) $this->option('client-id') : null;
        $minClientId = $this->option('min-client-id') ? (int) $this->option('min-client-id') : null;
        $chunk = (int) $this->option('chunk');

        $clientsQuery = Client::query()->select('id', 'name')->orderBy('id');
        if ($clientId) {
            $clientsQuery->where('id', $clientId);
        } elseif ($minClientId) {
            $clientsQuery->where('id', '>=', $minClientId);
        }
        $clients = $clientsQuery->withTrashed()->get();

        foreach ($clients as $client) {
            $this->processClient($client, $chunk);
        }

        $this->info("\nDone.");
    }


    private function processClient(Client $client, int $chunk): void
    {
        $this->info("\n-----------------------------------");
        $this->info("Client ID: {$client->id} -> {$client->name}");
        $this->info("-----------------------------------");

        $lastId = 0;
        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        $baseQuery = WhatsAppSendingMessage::query()
            ->where('client_id', $client->id)
            ->where('type', WhatsAppSendingMessage::WHATSAPP_META_API_TYPE)
            ->whereNotNull('meta_id')
            ->where(function ($q) {
                $q->whereNotNull('meta_status')->orWhereNotNull('error_message');
            })
            ->select('id', 'meta_id', 'meta_status', 'error_message')
            ->orderBy('id');

        $messages = $baseQuery->clone()->where('id', '>', $lastId)->take($chunk)->get();

        while ($messages->isNotEmpty()) {
            foreach ($messages as $wapMsg) {
                $conversationMsg = WhatsAppConversationMessage::where('metaMessageId', $wapMsg->meta_id)->first();

                if (!$conversationMsg) {
                    $this->line("  WapSendingMsg #{$wapMsg->id}: ConversationMessage not found");
                    $notFound++;
                    continue;
                }

                $updatedFields = [];

                // metaStatus
                if ($wapMsg->meta_status !== null && $conversationMsg->metaStatus != $wapMsg->meta_status) {
                    $conversationMsg->metaStatus = $wapMsg->meta_status;
                    $updatedFields[] = "metaStatus={$wapMsg->meta_status}";
                }

                // metaError: no pisar si ya tiene valor
                $conversationError = $conversationMsg?->metaError;
                if ($wapMsg->error_message && !$conversationError) {
                    $wapMsgMetaError = $this->decodeErrorMessage($wapMsg->error_message);
                    $conversationMsg->metaError = $wapMsgMetaError;
                    $errorStr = is_array($wapMsgMetaError) ? json_encode($wapMsgMetaError) : $wapMsgMetaError;
                    $updatedFields[] = "metaError={$errorStr}";
                }

                if (empty($updatedFields)) {
                    $this->line("  WapSendingMsg #{$wapMsg->id}: nothing to update");
                    $skipped++;
                    continue;
                }

                $conversationMsg->save();
                $fieldsStr = implode(', ', $updatedFields);
                $this->line(
                    "  WapSendingMsg #{$wapMsg->id}: ConversationMsg #{$conversationMsg->_id}: updated [{$fieldsStr}]"
                );
                $updated++;
            }

            $lastId = $messages->last()->id;
            $messages = $baseQuery->clone()->where('id', '>', $lastId)->take($chunk)->get();
        }

        $this->info("  Updated: {$updated} | Skipped: {$skipped} | Not found: {$notFound}");
    }


    private function decodeErrorMessage(string $errorMessage): array | string
    {
        $decoded = json_decode($errorMessage, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
            ? $decoded
            : $errorMessage
        ;
    }

}
