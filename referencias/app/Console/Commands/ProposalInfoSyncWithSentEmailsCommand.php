<?php

namespace App\Console\Commands;

use DateTime;
use App\Models\Lead;
use App\Models\Email;
use App\Models\LeadContact;
use App\Models\ProposalInfo;
use App\Helpers\SystemHelper;
use Illuminate\Console\Command;
use App\Models\LeadContactEmail;
use App\Models\LeadContactPhone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;


class ProposalInfoSyncWithSentEmailsCommand extends Command
{

    protected $loop = 0;
    protected $signature = 'proposals-info:sync-with-sent-emails {--chunk=} {--client-id=} {--ids=*}';
    protected $description = 'Tries to sync proposalsInfo (with no email data) with sent emails';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        SystemHelper::setMemoryLimitMB(600);

        $proposalInfoIds = $this->option('ids') ?? [];
        $chunk = (int) ($this->option('chunk') ?? 400);
        $clientId = (int) ($this->option('client-id') ?? 0);
            
        $continue = true;
        $minProposalInfoId = 72600;

        while ($continue) {
            $queryBuilder = ProposalInfo::query()
                ->limit($chunk)
                ->orderBy('id', 'asc')
                ->whereNull('email_ids')
                ->whereNull('email_ids_fixed_at')
                ->whereNull('whatsapp_sending_id')
                ->where('id', '>', $minProposalInfoId)
                ->select(['id', 'lead_id', 'user_id', 'created_at'])
            ;
            if ($clientId) {
                $queryBuilder->where('client_id', $clientId);
            }
            if ($proposalInfoIds) {
                $queryBuilder->whereIn('id', $proposalInfoIds);
            }
            
            $this->loop++;
            $this->info('- Loop: ' . $this->loop . ' - $minProposalInfoId: ' . $minProposalInfoId);

            $proposalsInfo = $queryBuilder->get();
            if ($proposalsInfo->isEmpty()) {
                $continue = false;
                break;
            }

            $minProposalInfoId = $proposalsInfo->last()->id;

            foreach ($proposalsInfo as $proposalInfo) {
                $potencialProposalEmails = Email::query()
                    ->orderBy('id', 'asc')
                    ->whereNotNull('sent_date')
                    ->where('is_proposal', true)
                    ->whereNull('migrated_date')
                    ->whereNotNull('external_id')
                    ->whereNull('automation_log_id')
                    ->whereNull('external_massive_id')
                    ->where('lead_id', $proposalInfo->lead_id)
                    ->where('user_id', $proposalInfo->user_id)
                    ->whereNotNull('individual_lead_send_hash')
                    ->select(['id', 'lead_id', 'user_id', 'is_proposal', 'individual_lead_send_hash', 'created_at'])
                    ->get()
                ;
                if ($potencialProposalEmails->isEmpty()) {
                    continue;
                }

                
                $presumableProposalEmails = new Collection();
                foreach ($potencialProposalEmails as $potencialProposalEmail) {
                    $emailCreatedBeforeProposal = $potencialProposalEmail->created_at > $proposalInfo->created_at;
                    if ($emailCreatedBeforeProposal) {
                        continue;
                    }

                    $diffSeconds = $potencialProposalEmail->created_at->diffInSeconds($proposalInfo->created_at);
                    if ($diffSeconds < 60) {
                        $presumableProposalEmails->push($potencialProposalEmail);
                    }
                }
                if ($presumableProposalEmails->isEmpty()) {
                    continue;
                }

                // $presumableEmailsToShow = $presumableProposalEmails->map(function ($email) {
                //     return [
                //         'id' => $email->id,
                //         'lead_id' => $email->lead_id,
                //         'user_id' => $email->user_id,
                //         'is_proposal' => $email->is_proposal,
                //         'created_at' => $email->created_at->format('Y-m-d H:i:s'),
                //         'individual_lead_send_hash' => $email->individual_lead_send_hash,
                //     ];
                // })->toArray();
                $presumableProposalEmailIds = $presumableProposalEmails->pluck('id');

                $leadHashesCount = $presumableProposalEmails->pluck('individual_lead_send_hash')->unique()->count();
                $areSameSending = $leadHashesCount == 1;
                if (!$areSameSending) {
                    $existentProposalsInfo = ProposalInfo::query()
                        ->where('lead_id', $proposalInfo->lead_id)
                        ->whereNotNull('email_ids')
                        ->select(['id', 'lead_id', 'user_id', 'email_ids', 'created_at'])
                        ->get()
                    ;
                    
                    // $this->warn('===================================================================================');
                    $this->warn('== NOT THE SAME SENDING');
                    // dump('$existentProposalsInfo', $existentProposalsInfo->toArray());
                    dump('$presumableProposalEmailIds', $presumableProposalEmailIds->toArray());
                    
                    $presumableProposalEmails = $presumableProposalEmails->filter(
                        function ($presumableProposalEmail) use ($existentProposalsInfo) {
                            foreach ($existentProposalsInfo as $existentProposalInfo) {
                                if ($existentProposalInfo->hasEmailId($presumableProposalEmail->id)) {
                                    return false;
                                }
                            }
                            return true;
                        }
                    );

                    $leadHashesCount = $presumableProposalEmails->pluck('individual_lead_send_hash')->unique()->count();
                    $areSameSending = $leadHashesCount == 1;

                    if ($presumableProposalEmails->isEmpty() || !$areSameSending) {
                        continue;
                    }
                    $presumableProposalEmailIds = $presumableProposalEmails->pluck('id');

                    // dump('$presumableProposalEmailIds', $presumableProposalEmailIds->toArray());
                    // dump('$proposalInfo->id', $proposalInfo->id);
                    // $this->warn('===================================================================================');
                }


                $existentProposalsInfo = ProposalInfo::query()
                    ->whereNotNull('email_ids')
                    ->whereNull('email_ids_fixed_at')
                    ->where('lead_id', $proposalInfo->lead_id)
                    ->select(['id', 'lead_id', 'user_id', 'email_ids', 'created_at'])
                    ->get()
                ;

                $proposalWasAlreadyCreated = false;
                if ($existentProposalsInfo->isNotEmpty()) {
                    foreach ($existentProposalsInfo as $existentProposalInfo) {
                        $existentEmailIds = collect($existentProposalInfo->email_ids);
                        $intersectedEmailIds = $existentEmailIds->intersect($presumableProposalEmailIds);
                        if ($intersectedEmailIds->isNotEmpty()) {
                            $proposalWasAlreadyCreated = true;
                        }
                    }
                }
                if ($proposalWasAlreadyCreated) {
                    continue;
                }

                dump('$proposalInfo->id', $proposalInfo->id);
                dump('$presumableProposalEmailIds', $presumableProposalEmailIds->toArray());

                $proposalInfo->email_ids = $presumableProposalEmailIds;
                $proposalInfo->email_ids_fixed_at = new DateTime('now');
                $proposalInfo->saveOrFail();

                $this->info(PHP_EOL . '---------------------------------' . PHP_EOL . PHP_EOL);
            }
        }
    }

}
