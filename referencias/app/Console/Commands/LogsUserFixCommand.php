<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;


class LogsUserFixCommand extends Command
{

    protected $cachedUserIds = [];
    protected $cachedLeadIds = [];
    protected $signature = 'logs:fix-user {--chunk=} {--offset=}';
    protected $description = 'Fix user object at Logs documents';


    public function __construct()
    {
        parent::__construct();
    }


    // Multiple docs version
    public function handle()
    {
        die ('deprecado!');

        
        $limit = (int) ($this->option('chunk') ?? 500);
        $offset = (int) ($this->option('offset') ?? 0);
        // $eventsLogAPIHelper = resolve(EventsLogAPIHelper::class);
        
        $filters = [
            'limit' => $limit,
            // 'offset' => $offset, // No usa offset, por que va cambiando el user y dejando de existir log.user.id
            'system' => 'clienty_crm',
            'event' => ['lead_tag_added', 'lead_tag_deleted', 'lead_status_updated'],
            'log_filters' => [
                'log.user.id' => ['$exists' => true],
            ],
            //'fields' => ['hash', 'log.user.id', 'log.lead.id'],
        ];
        $result = $eventsLogAPIHelper->findLogs($filters);
        $documents = $result['data'] ?? [];
        // var_dump($documents);
        
        while ($documents) {
            $docsInfo = [];
            foreach ($documents as $doc) {
                $clientIdByLead = $this->getClientIdByLead($doc);
                $clientIdByUser = $this->getClientIdByUser($doc);
                if (!$clientIdByLead || !$clientIdByUser) {
                    continue;
                }
                $docEvent = $doc['event'];
                $docClientId = $doc['log']['client_id'];
                // $this->info($clientIdByLead . '- ' . $clientIdByUser);
                if ($clientIdByLead != $clientIdByUser) {
                    $docsInfo[] = [
                        'id' => $doc['_id']['$oid'],
                        'user' => 'SYSTEM',
                        'hash' => $doc['hash'],
                        'client_id' => $clientIdByLead,
                    ];
                    $msg = "-\e[0m Log ID: \033[36m\e[1m{$doc['_id']['$oid']}\e[0m - ";
                    $msg .= "event: \033[33m\e[1m{$docEvent}\e[0m - ";
                    $msg .= "Old User.client_id: \033[33m\e[1m{$clientIdByUser}\e[0m - ";
                    $msg .= "New client_id: \033[32m\e[1m{$clientIdByLead}\e[0m";
                    $this->info($msg);
                }
            }
            
            if ($docsInfo) {
                $response = $eventsLogAPIHelper->setMultipleLogUserAndClientId($docsInfo);
                $responseData = $response['data'] ?? [];
                $docsMatched = $responseData['docsMatched'];
                $docsModified = $responseData['docsModified'];
                $this->info("- Matched: {$docsMatched} docs. Modified: {$docsModified} docs.");
            }

            // $filters['offset'] += $limit;
            // $this->info("- Offset: {$filters['offset']}");
            $this->info("-------------------------------------------------------");

            $result = $eventsLogAPIHelper->findLogs($filters);
            $documents = $result['data'] ?? [];
        }
    }



    protected function getClientIdByLead(array $doc): ?int
    {
        $mongoLeadId = $doc['log']['lead']['id'] ?? null;
        if ($mongoLeadId) {
            if ($this->cachedLeadIds[$mongoLeadId] ?? null) {
                return $this->cachedLeadIds[$mongoLeadId];
            }

            $lead = Lead::withTrashed()->find($doc['log']['lead']['id']);
            if (!$lead) {
                // $this->error('- Lead not found. ID: ' . $doc['log']['lead']['id']);
                return null;
            }
            $this->cachedLeadIds[$mongoLeadId] = $lead->client_id;
            return $lead->client_id;
        }

        return null; // Falla por el tipado si llega acá.
    }


    protected function getClientIdByUser(array $doc): ?int
    {
        $mongoUserId = $doc['log']['user']['id'] ?? null;
        if ($mongoUserId) {
            if ($this->cachedUserIds[$mongoUserId] ?? null) {
                return $this->cachedUserIds[$mongoUserId];
            }

            $user = User::withTrashed()->find($doc['log']['user']['id']);
            if (!$user) {
                // $this->error('- User not found. ID: ' . $doc['log']['user']['id']);
                return null;
            }
            $this->cachedUserIds[$mongoUserId] = $user->client_id;
            return $user->client_id;
        }

        return null; // Falla por el tipado si llega acá.
    }

}
