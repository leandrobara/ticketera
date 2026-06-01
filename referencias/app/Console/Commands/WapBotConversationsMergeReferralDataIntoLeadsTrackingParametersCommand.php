<?php

namespace App\Console\Commands;

use Throwable;
use App\Models\Lead;
use App\Models\Client;
use Illuminate\Support\Arr;
use Illuminate\Console\Command;
use App\Services\API\WapBot\WapBotConversationService;


class WapBotConversationsMergeReferralDataIntoLeadsTrackingParametersCommand extends Command
{

    protected $description = 'Merge WapBotConversation referralData into Leads tracking_parameters';
    protected $signature =
        'wap-bot-conversations:merge-referral-data-into-leads-tracking-parameters
        {--client-id=}
        {--min-client-id=}
        {--chunk=500}';


    public function handle(WapBotConversationService $conversationService)
    {
        $clientId = $this->option('client-id') ? (int) $this->option('client-id') : null;
        $minClientId = $this->option('min-client-id') ? (int) $this->option('min-client-id') : null;
        $chunk = max(1, (int) $this->option('chunk'));

        $clientsQuery = Client::query()->select('id', 'name')->orderBy('id');
        if ($clientId) {
            $clientsQuery->where('id', $clientId);
        } elseif ($minClientId) {
            $clientsQuery->where('id', '>=', $minClientId);
        }

        $clients = $clientsQuery->withTrashed()->get();
        if ($clients->isEmpty()) {
            $this->warn('No clients found for the given filters.');
            return self::SUCCESS;
        }

        $globalStats = $this->newStats();

        foreach ($clients as $client) {
            $this->info("\n-----------------------------------");
            $this->info("- Client ID: {$client->id} -> {$client->name}");
            $this->info("-----------------------------------");

            $clientStats = $this->processClient($client, $conversationService, $chunk);
            $globalStats = $this->sumStats($globalStats, $clientStats);

            $this->info(
                "  Summary: processed={$clientStats['processed']}"
                . " updated={$clientStats['updated']}"
                . " skipped_referral={$clientStats['skipped_referral']}"
                . " skipped_lead_not_found={$clientStats['skipped_lead_not_found']}"
                . " skipped_client_mismatch={$clientStats['skipped_client_mismatch']}"
                . " skipped_no_changes={$clientStats['skipped_no_changes']}"
                . " errors={$clientStats['errors']}"
            );
        }

        $this->info("\nGlobal summary:");
        $this->info(
            "processed={$globalStats['processed']}"
            . " updated={$globalStats['updated']}"
            . " skipped_referral={$globalStats['skipped_referral']}"
            . " skipped_lead_not_found={$globalStats['skipped_lead_not_found']}"
            . " skipped_client_mismatch={$globalStats['skipped_client_mismatch']}"
            . " skipped_no_changes={$globalStats['skipped_no_changes']}"
            . " errors={$globalStats['errors']}"
        );

        return self::SUCCESS;
    }


    private function processClient(
        Client $client,
        WapBotConversationService $conversationService,
        int $chunk,
    ): array {
        $stats = $this->newStats();

        $conversationChunks = $conversationService
            ->cursorByClientWithLeadAndReferralData($client->id)
            ->chunk($chunk)
        ;

        foreach ($conversationChunks as $conversations) {
            foreach ($conversations as $conversation) {
                $stats['processed']++;

                $conversationId = (string) $conversation->id;
                $leadId = $conversation->leadId ? (int) $conversation->leadId : null;

                try {
                    $flattenedReferralData = $this->flattenReferralData($conversation->referralData ?? null);
                    if (empty($flattenedReferralData)) {
                        $stats['skipped_referral']++;
                        $this->logDocument(
                            status: 'skipped: referralData empty',
                            clientId: $client->id,
                            leadId: $leadId,
                            conversationId: $conversationId,
                        );
                        continue;
                    }

                    $lead = Lead::find($leadId);
                    if (!$lead) {
                        $stats['skipped_lead_not_found']++;
                        $this->logDocument(
                            status: 'skipped: lead not found',
                            clientId: $client->id,
                            leadId: $leadId,
                            conversationId: $conversationId,
                        );
                        continue;
                    }

                    if ((int) $lead->client_id !== (int) $client->id) {
                        $stats['skipped_client_mismatch']++;
                        $this->logDocument(
                            status: 'skipped: client mismatch',
                            clientId: $client->id,
                            leadId: (int) $lead->id,
                            conversationId: $conversationId,
                            extra: "lead_client_id={$lead->client_id}",
                        );
                        continue;
                    }

                    $currentTrackingParameters = is_array($lead->tracking_parameters)
                        ? Arr::dot($lead->tracking_parameters)
                        : []
                    ;
                    $mergedTrackingParameters = array_replace($currentTrackingParameters, $flattenedReferralData);

                    if ($mergedTrackingParameters === $currentTrackingParameters) {
                        $stats['skipped_no_changes']++;
                        $this->logDocument(
                            status: 'skipped: no changes',
                            clientId: $client->id,
                            leadId: (int) $lead->id,
                            conversationId: $conversationId,
                            extra: 'keys=' . count($flattenedReferralData),
                        );
                        continue;
                    }

                    $lead->tracking_parameters = $mergedTrackingParameters;
                    if (!$lead->save()) {
                        throw new \RuntimeException('Lead save returned false.');
                    }

                    $stats['updated']++;
                    $this->logDocument(
                        status: 'updated',
                        clientId: $client->id,
                        leadId: (int) $lead->id,
                        conversationId: $conversationId,
                        extra: 'keys=' . count($flattenedReferralData),
                    );
                } catch (Throwable $e) {
                    $stats['errors']++;
                    $this->logDocument(
                        status: 'error',
                        clientId: $client->id,
                        leadId: $leadId,
                        conversationId: $conversationId,
                        extra: $e->getMessage(),
                        isError: true,
                    );
                }
            }
        }
        return $stats;
    }


    private function flattenReferralData(mixed $referralData): array
    {
        if (!is_array($referralData) || empty($referralData)) {
            return [];
        }
        return Arr::dot($referralData);
    }


    private function logDocument(
        string $status,
        int $clientId,
        ?int $leadId,
        string $conversationId,
        ?string $extra = null,
        bool $isError = false,
    ): void {
        $message = "[{$status}] client_id={$clientId} lead_id=" . ($leadId ?? 'null')
            . " conversation_id={$conversationId}";

        if ($extra) {
            $message .= " {$extra}";
        }
        if ($isError) {
            $this->error($message);
            return;
        }
        $this->line($message);
    }


    private function newStats(): array
    {
        return [
            'processed' => 0,
            'updated' => 0,
            'skipped_referral' => 0,
            'skipped_lead_not_found' => 0,
            'skipped_client_mismatch' => 0,
            'skipped_no_changes' => 0,
            'errors' => 0,
        ];
    }


    private function sumStats(array $baseStats, array $statsToAdd): array
    {
        foreach ($statsToAdd as $key => $value) {
            $baseStats[$key] += $value;
        }
        return $baseStats;
    }

}
