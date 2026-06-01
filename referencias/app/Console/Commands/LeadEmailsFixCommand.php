<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use App\Models\LeadContactEmail;
use Illuminate\Support\Facades\Artisan;


class LeadEmailsFixCommand extends Command
{

    protected $cachedUserIds = [];
    protected $cachedLeadIds = [];
    protected $signature = 'lead-emails:fix {--chunk=} {--offset=}';
    protected $description = 'Fix invalid lead emails';


    public function __construct()
    {
        parent::__construct();
    }


    // Multiple docs version
    public function handle()
    {
        die ('deprecado!');

        $limit = (int) ($this->option('chunk') ?? 10000);
        $offset = (int) ($this->option('offset') ?? 0);
        $queryBuilder = LeadContactEmail::query();
        
        $leadContactEmails = LeadContactEmail::query()/*->where('id', 1)*/->skip($offset)->take($limit)->get();
        while ($leadContactEmails->isNotEmpty()) {
            foreach ($leadContactEmails as $leadContactEmail) {
                $emailAddr = $leadContactEmail->email;
                $isValidEmail = filter_var($emailAddr, FILTER_VALIDATE_EMAIL) ? true : false;
                if ($isValidEmail) {
                    continue;
                }

                $trimmedEmailAddr = trim($emailAddr);
                $isValidTrimmedEmail = filter_var($trimmedEmailAddr, FILTER_VALIDATE_EMAIL) ? true : false;

                $fixedEmailAddr1 = trim(str_replace(['>', '<'], ['', ''], $trimmedEmailAddr));
                $isValidFixedEmailAddr1 = filter_var($fixedEmailAddr1, FILTER_VALIDATE_EMAIL) ? true : false;
                
                $isValidFixedEmailAddr2 = false;
                $endsWithGmail = Str::endsWith($trimmedEmailAddr, '@gmail');
                if ($endsWithGmail) {
                    $fixedEmailAddr2 = $trimmedEmailAddr . '.com';
                    $isValidFixedEmailAddr2 = filter_var($fixedEmailAddr2, FILTER_VALIDATE_EMAIL) ? true : false;
                }

                $isValidFixedEmailAddr3 = false;
                $endsWithHotmail = Str::endsWith($trimmedEmailAddr, '@gmail');
                if ($endsWithHotmail) {
                    $fixedEmailAddr3 = $trimmedEmailAddr . '.com';
                    $isValidFixedEmailAddr3 = filter_var($fixedEmailAddr3, FILTER_VALIDATE_EMAIL) ? true : false;
                }

                if ($isValidTrimmedEmail) {
                    $leadContactEmail->email = $trimmedEmailAddr;
                    $leadContactEmail->hash = LeadContactEmail::buildHash($trimmedEmailAddr);
                    $leadContactEmail->saveOrFail();
                    // $leadContactEmail->lead->searchable();

                    $msg = "Trimmed: \033[36m\e[1m\"{$trimmedEmailAddr}\"\e[0m";
                } elseif ($isValidFixedEmailAddr1) {
                    $leadContactEmail->email = $fixedEmailAddr1;
                    $leadContactEmail->hash = LeadContactEmail::buildHash($fixedEmailAddr1);
                    $leadContactEmail->saveOrFail();
                    // $leadContactEmail->lead->searchable();

                    $msg = "Fixed 1: ";
                    $msg .= "\033[32m\e[1m\"{$emailAddr}\"\e[0m -> \033[32m\e[1m\"{$fixedEmailAddr1}\"\e[0m";
                } elseif ($isValidFixedEmailAddr2) {
                    $leadContactEmail->email = $fixedEmailAddr2;
                    $leadContactEmail->hash = LeadContactEmail::buildHash($fixedEmailAddr2);
                    $leadContactEmail->saveOrFail();
                    // $leadContactEmail->lead->searchable();

                    $msg = "Fixed 2: ";
                    $msg .= "\033[32m\e[1m\"{$emailAddr}\"\e[0m -> \033[32m\e[1m\"{$fixedEmailAddr2}\"\e[0m";
                } elseif ($isValidFixedEmailAddr3) {
                    $leadContactEmail->email = $fixedEmailAddr3;
                    $leadContactEmail->hash = LeadContactEmail::buildHash($fixedEmailAddr3);
                    $leadContactEmail->saveOrFail();
                    // $leadContactEmail->lead->searchable();

                    $msg = "Fixed 3: ";
                    $msg .= "\033[32m\e[1m\"{$emailAddr}\"\e[0m -> \033[32m\e[1m\"{$fixedEmailAddr3}\"\e[0m";
                } else {
                    $msg = "DELETED: \033[33m\e[1m{$emailAddr}\e[0m";
                    $leadContactEmail->delete();
                    // $leadContactEmail->lead->searchable();
                }

                $this->info($msg);
            }
            $this->info("- Offset: {$offset}");
            $this->info("-------------------------------------------------------");

            $offset = $offset + $limit;
            $leadContactEmails = LeadContactEmail::query()/*->where('id', 1)*/->skip($offset)->take($limit)->get();
        }
        
        
        // $result = $eventsLogAPIHelper->findLogs($filters);
        // while ($documents) {
        //     $docsInfo = [];
        //     foreach ($documents as $doc) {
        //         $clientIdByLead = $this->getClientIdByLead($doc);
        //         $clientIdByUser = $this->getClientIdByUser($doc);
        //         if (!$clientIdByLead || !$clientIdByUser) {
        //             continue;
        //         }
        //         $docEvent = $doc['event'];
        //         $docClientId = $doc['log']['client_id'];
        //         // $this->info($clientIdByLead . '- ' . $clientIdByUser);
        //         if ($clientIdByLead != $clientIdByUser) {
        //             $docsInfo[] = [
        //                 'id' => $doc['_id']['$oid'],
        //                 'user' => 'SYSTEM',
        //                 'hash' => $doc['hash'],
        //                 'client_id' => $clientIdByLead,
        //             ];
        //             $msg = "-\e[0m Log ID: \033[36m\e[1m{$doc['_id']['$oid']}\e[0m - ";
        //             $msg .= "event: \033[33m\e[1m{$docEvent}\e[0m - ";
        //             $msg .= "Old User.client_id: \033[33m\e[1m{$clientIdByUser}\e[0m - ";
        //             $msg .= "New client_id: \033[32m\e[1m{$clientIdByLead}\e[0m";
        //             $this->info($msg);
        //         }
        //     }
            
        //     if ($docsInfo) {
        //         $response = $eventsLogAPIHelper->setMultipleLogUserAndClientId($docsInfo);
        //         $responseData = $response['data'] ?? [];
        //         $docsMatched = $responseData['docsMatched'];
        //         $docsModified = $responseData['docsModified'];
        //         $this->info("- Matched: {$docsMatched} docs. Modified: {$docsModified} docs.");
        //     }

        //     // $filters['offset'] += $limit;
        //     // $this->info("- Offset: {$filters['offset']}");
        //     $this->info("-------------------------------------------------------");

        //     $result = $eventsLogAPIHelper->findLogs($filters);
        //     $documents = $result['data'] ?? [];
        // }
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
