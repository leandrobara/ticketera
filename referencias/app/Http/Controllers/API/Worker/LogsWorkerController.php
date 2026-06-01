<?php

namespace App\Http\Controllers\API\Worker;

use App\Models\User;
use App\Models\Lead;
use App\Models\Task;
use App\Models\Note;
use App\Models\Client;
use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\API\BaseAPIController;


class LogsWorkerController extends BaseAPIController
{

    protected $cachedUserIds = [];
    protected $cachedLeadIds = [];


    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(2000);
    }


    // Versión múltiple
    public function setClientToLogs(Request $req)
    {
        die('deprecado');

        
        // $limit = 100;
        // $offset = 20000;
        // $eventsLogAPIHelper = resolve(EventsLogAPIHelper::class);
        
        // $filters = [
        //     'limit' => $limit,
        //     'offset' => $offset,
        //     'system' => 'clienty_crm',
        //     'fields' => ['hash', 'log.user.id', 'log.lead.id'],
        // ];
        // $result = $eventsLogAPIHelper->findLogs($filters);
        // $documents = $result['data'] ?? [];
        
        // while ($documents) {
        //     $docsInfo = [];
        //     foreach ($documents as $doc) {
        //         if ($doc['log']['client_id'] ?? null) {
        //             continue;
        //         }
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
        //         echo "- Matched: {$docsMatched} docs. Modified: {$docsModified} docs. <br>";
        //         SystemHelper::doFlush();
        //     }

        //     $filters['offset'] += $limit;
        //     echo "- Offset: {$filters['offset']} <hr>";

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
                // $this->error('- User not found. ID: ' . $doc['log']['user']['id']);
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
                // $this->error('- Lead not found. ID: ' . $doc['log']['lead']['id']);
                return null;
            }
            $this->cachedLeadIds[$mongoLeadId] = $lead->client_id;
            return $lead->client_id;
        }

        return null; // Falla por el tipado si llega acá.
    }
    
}
