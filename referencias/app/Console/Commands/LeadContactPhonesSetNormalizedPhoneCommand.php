<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\LeadContactPhone;
use Illuminate\Console\Command;


class LeadContactPhonesSetNormalizedPhoneCommand extends Command
{

    protected $description = 'Set normalized_phone and normalized_hash fields for LeadContactPhones';
    protected $signature = 'lead-contact-phones:set-normalized-phone {--clientId=} {--chunk=500}';


    public function handle()
    {
        $clientId = (int) ($this->option('clientId') ?? 0);
        $chunk = (int) $this->option('chunk');

        if ($clientId <= 0) {
            $this->error('clientId must be a valid positive integer.');
            return self::FAILURE;
        }

        if ($chunk <= 0) {
            $this->error('chunk must be a valid positive integer.');
            return self::FAILURE;
        }

        $client = Client::withTrashed()
            ->with('clientSettings')
            ->select('id', 'name', 'country_code', 'client_settings_id')
            ->find($clientId)
        ;

        if (!$client) {
            $this->error("Client ID {$clientId} not found.");
            return self::FAILURE;
        }

        $this->info("\n-----------------------------------");
        $this->info("- Client ID: {$client->id} -> {$client->name} (country: {$client->country_code})");
        $this->info("-----------------------------------");

        $updated = 0;
        $skipped = 0;
        $unchanged = 0;
        $lastId = 0;

        $baseQuery = LeadContactPhone::query()
            ->where('client_id', $client->id)
            ->select('id', 'phone', 'client_id', 'normalized_phone', 'normalized_hash')
            ->orderBy('id')
        ;

        $phones = $baseQuery->clone()->where('id', '>', $lastId)->take($chunk)->get();

        while ($phones->isNotEmpty()) {
            $bulkUpdates = [];

            foreach ($phones as $leadContactPhone) {
                $leadContactPhone->setRelation('client', $client);
                $normalizedPhone = $leadContactPhone->getWhatsAppFormattedPhone(
                    $client->country_code, $client->clientSettings
                );

                if ($normalizedPhone === '') {
                    $skipped++;
                    continue;
                }

                $normalizedHash = LeadContactPhone::buildNormalizedHash($normalizedPhone);
                if (
                    $leadContactPhone->normalized_phone === $normalizedPhone
                    && $leadContactPhone->normalized_hash === $normalizedHash
                ) {
                    $unchanged++;
                    continue;
                }

                $bulkUpdates[] = [
                    'id' => $leadContactPhone->id,
                    'normalized_phone' => $normalizedPhone,
                    'normalized_hash' => $normalizedHash,
                ];
            }

            if (!empty($bulkUpdates)) {
                $this->batchUpdate($bulkUpdates);
                $updated += count($bulkUpdates);
                $this->line("  Batch updated: {$updated} so far (last ID: {$phones->last()->id})");
            }

            $lastId = $phones->last()->id;
            $phones = $baseQuery->clone()->where('id', '>', $lastId)->take($chunk)->get();
        }

        $this->info("  Updated: {$updated} | Unchanged: {$unchanged} | Skipped (empty phone): {$skipped}");
        $this->info("\nDone.");
        return self::SUCCESS;
    }


    private function batchUpdate(array $rows): void
    {
        $ids = [];
        $phoneCases = [];
        $hashCases = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $phone = addslashes($row['normalized_phone']);
            $hash = addslashes($row['normalized_hash']);

            $ids[] = $id;
            $phoneCases[] = "WHEN {$id} THEN '{$phone}'";
            $hashCases[] = "WHEN {$id} THEN '{$hash}'";
        }

        $idsStr = implode(',', $ids);
        $phoneCasesStr = implode(' ', $phoneCases);
        $hashCasesStr = implode(' ', $hashCases);

        $sql = "UPDATE LeadsContactsPhones SET
            normalized_phone = CASE id {$phoneCasesStr} END,
            normalized_hash = CASE id {$hashCasesStr} END,
            updated_at = NOW()
            WHERE id IN ({$idsStr})";

        \Illuminate\Support\Facades\DB::statement($sql);
    }

}
