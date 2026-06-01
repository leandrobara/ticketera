<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use App\Models\LeadContactEmail;
use App\Models\MongoDB\GmailMessageLog;
use Illuminate\Support\Facades\Artisan;
use App\DTO\GoogleAPI\GoogleAPIGmailMessageDTO;


class GmailMessageLogsHashFixCommand extends Command
{

    protected $cachedClients = [];
    protected $signature = 'gmail-message-logs:hash-fix';
    protected $description = 'Fix gmail message logs hash';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $offset = 0;
        $limit = 100;
        $gmailMessageLogs = GmailMessageLog::orderBy('sentDateTs')->skip($offset)->take($limit)->get();

        while ($gmailMessageLogs->isNotEmpty()) {
            $this->info("- Offset: {$offset}");
            $this->info("-------------------------------------------------------");

            foreach ($gmailMessageLogs as $gmailMessageLog) {
                $dto = GoogleAPIGmailMessageDTO::buildFromMongoDoc($gmailMessageLog);

                $clientId = (int) $dto->clientyMetadata['client']['id'];
                $client = $this->cachedClients[$clientId] ?? Client::withTrashed()->findOrFail($clientId);
                $this->cachedClients[$clientId] = $client;

                $oldHash = $gmailMessageLog->hash;
                $newHash = GmailMessageLog::buildHash($client, $dto->toArray());
                $gmailMessageLog->hash = $newHash;

                $gmailMessageLog->save();

                $infoArr = [
                    "- ID: {$gmailMessageLog->id}",
                    "- Sent date: {$gmailMessageLog->sentDate->format('Y-m-d H:i:s')}",
                    "- Old hash: {$oldHash}",
                    "- New hash: {$gmailMessageLog->hash}",
                ];
                $this->info(implode(' - ', $infoArr));
            }

            $offset = $offset + $limit;
            $gmailMessageLogs = GmailMessageLog::orderBy('sentDateTs')->skip($offset)->take($limit)->get();
        }
    }

}
