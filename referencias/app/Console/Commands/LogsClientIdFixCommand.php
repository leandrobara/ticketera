<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;


class LogsClientIdFixCommand extends Command
{

    protected $cachedUserIds = [];
    protected $cachedLeadIds = [];
    protected $signature = 'logs:fix-client-id {--chunk=} {--offset=}';
    protected $description = 'Fix client_id field at Logs documents';


    public function __construct()
    {
        parent::__construct();
    }


    // Multiple docs version
    public function handle()
    {
        die('deprecado!');


        // $this->warn('This command fix all logs documents without client_id field, it may take a long time!');

        // $limit = (int) ($this->option('chunk') ?? 500);
        // $offset = (int) ($this->option('offset') ?? 0);
        // $eventsLogAPIHelper = resolve(EventsLogAPIHelper::class);
        
        // $filters = [
        //     'limit' => $limit,
        //     'offset' => $offset,
        //     'system' => 'clienty_crm',
        //     // 'log_filters' => [
        //     //     'log.client_id' => ['$exists' => false],
        //     // ],
        //     'fields' => ['hash', 'log.user.id', 'log.lead.id'],
        // ];
        // $result = $eventsLogAPIHelper->findLogs($filters);
        // $documents = $result['data'] ?? [];
        
        // while ($documents) {
        //     $docsInfo = [];
        //     foreach ($documents as $doc) {
        //         // if ($doc['log']['client_id'] ?? null) {
        //         //     continue;
        //         // }
        //         $clientId = $this->getClientId($doc);
        //         if (!$clientId) {
        //             continue;
        //         }
        //         $docsInfo[] = [
        //             'hash' => $doc['hash'],
        //             'id' => $doc['_id']['$oid'],
        //             'client_id' => $this->getClientId($doc),
        //         ];
        //     }
            
        //     if ($docsInfo) {
        //         $response = $eventsLogAPIHelper->setMultipleLogClientId($docsInfo);
        //         $responseData = $response['data'] ?? [];
        //         $docsMatched = $responseData['docsMatched'];
        //         $docsModified = $responseData['docsModified'];
        //         $this->info("- Matched: {$docsMatched} docs. Modified: {$docsModified} docs.");
        //     }

        //     $filters['offset'] += $limit;
        //     $this->info("- Offset: {$filters['offset']}");
        //     $this->info("-------------------------------------------------------");

        //     $result = $eventsLogAPIHelper->findLogs($filters);
        //     $documents = $result['data'] ?? [];
        // }
    }


    protected function getClientId(array $doc): ?int
    {
        $mongoUserId = $doc['log']['user']['id'] ?? null;
        if ($mongoUserId) {
            if ($this->cachedUserIds[$mongoUserId] ?? null) {
                return $this->cachedUserIds[$mongoUserId];
            }

            $user = User::withTrashed()->find($doc['log']['user']['id']);
            if (!$user) {
                $this->error('- User not found. ID: ' . $doc['log']['user']['id']);
                return null;
            }
            $this->cachedUserIds[$mongoUserId] = $user->client_id;
            return $user->client_id;
        }

        $mongoLeadId = $doc['log']['lead']['id'] ?? null;
        if ($mongoLeadId) {
            if ($this->cachedLeadIds[$mongoLeadId] ?? null) {
                return $this->cachedLeadIds[$mongoLeadId];
            }

            $lead = Lead::withTrashed()->find($doc['log']['lead']['id']);
            if (!$lead) {
                $this->error('- Lead not found. ID: ' . $doc['log']['lead']['id']);
                return null;
            }
            $this->cachedLeadIds[$mongoLeadId] = $lead->client_id;
            return $lead->client_id;
        }

        return null; // Falla por el tipado si llega acá.
    }

}
