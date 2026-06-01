<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadContact;
use Illuminate\Console\Command;
use App\Models\LeadContactEmail;
use App\Models\LeadContactPhone;
use Illuminate\Support\Facades\Artisan;


class LeadContactsFixDeletedPhonesAndEmailsCommand extends Command
{

    protected $loop = 0;
    protected $signature = 'lead-contacts:fix-deleted-phones-and-emails {--chunk=} {--client-id=} {--ids=*}';
    protected $description = 'Mark leadContactEmails and leadContactPhones as deleted where leadContacts were deleted';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $leadContactIds = $this->option('ids') ?? [];
        $chunk = (int) ($this->option('chunk') ?? 100);
        $clientId = (int) ($this->option('client-id') ?? 0);

        $queryBuilder = LeadContact::query()->withTrashed()->whereNotNull('deleted_at')->select(['id']);
        if ($clientId) {
            $queryBuilder->where('client_id', $clientId);
        }
        if ($leadContactIds) {
            $queryBuilder->whereIn('id', $leadContactIds);
        }
        $queryBuilder->chunk($chunk, function ($leadContacts) {
            $this->loop++;
            $this->info('- Loop: ' . $this->loop);

            $leadContactIds = $leadContacts->pluck('id');
            $nonDeletedLeadContactEmails = LeadContactEmail::whereIn('lead_contact_id', $leadContactIds)->get();
            if ($nonDeletedLeadContactEmails->isNotEmpty()) {
                foreach ($nonDeletedLeadContactEmails as $nonDeletedLeadContactEmail) {
                    $nonDeletedLeadContactEmail->delete();
                    $this->warn('- LeadContactEmail ID: ' . $nonDeletedLeadContactEmail->id . ' - deleted');
                }
            }

            $nonDeletedLeadContactPhones = LeadContactPhone::whereIn('lead_contact_id', $leadContactIds)->get();
            if ($nonDeletedLeadContactPhones->isNotEmpty()) {
                foreach ($nonDeletedLeadContactPhones as $nonDeletedLeadContactPhone) {
                    $nonDeletedLeadContactPhone->delete();
                    $this->warn('- LeadContactPhone ID: ' . $nonDeletedLeadContactPhone->id . ' - deleted');
                }
            }
        });
    }

}
