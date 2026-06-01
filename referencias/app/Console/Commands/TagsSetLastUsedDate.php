<?php

namespace App\Console\Commands;

use App\Models\Tag;
use App\Models\Client;
use Illuminate\Console\Command;
use App\Models\MongoDB\EventLog;
use App\Services\API\TagService;


class TagsSetLastUsedDate extends Command
{

    protected $description = 'Set last used date on tags';
    protected $signature = 'tags:set-last-used-date {--chunk=} {--client-id=}';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $chunk = (int) ($this->option('chunk') ?? 1000);
        $clientId = (int) ($this->option('client-id') ?? 0);

        $queryBuilder = Client::query();
        $tagService = resolve(TagService::class);

        if ($clientId) {
            $queryBuilder->where('id', $clientId);
        }

        $clients = $queryBuilder->withTrashed()->orderBy('id', 'asc')->get();
        foreach ($clients as $client) {
            $this->info("\n-----------------------------------");
            $this->info("- Client ID: {$client->id} -> {$client->name}");
            $this->info("-----------------------------------\n");

            $queryBuilder = Tag::query()->select('id', 'name', 'client_id', 'last_used_date')
                ->where('client_id', $client->id)
                ->where('last_used_date', null)
                ->orderBy('id', 'asc')
                ->limit($chunk)
            ;
            $tags = $queryBuilder->get();
            while ($tags->isNotEmpty()) {
                foreach ($tags as $tag) {
                    $eventLog = EventLog::where('log.client_id', $client->id)
                        ->where('log.tag.id', $tag->id)
                        ->where('system', 'clienty_crm')
                        ->where('event', 'lead_tag_added')
                        ->orderBy('createdAtTs', 'desc')
                        ->limit(1)
                        ->first()
                    ;
                    if (!$eventLog) {
                        $this->error("Tag \"{$tag->name}\" (ID: {$tag->id}) - NOT UPDATED - LOG NOT FOUND");
                        continue;
                    }
                    
                    $tagService->update($tag, ['last_used_date' => $eventLog->createdAt]);
                    $this->info("Tag \"{$tag->name}\" (ID: {$tag->id}) - UPDATED USE DATE: {$eventLog->createdAt}");
                }
                $this->info("<---------->");
                $tags = (clone $queryBuilder)->where('id', '>', $tags->last()->id)->limit($chunk)->get();
            }
        }

    }

}
