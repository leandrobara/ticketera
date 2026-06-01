<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppSending;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;


class WhatsAppSendingsSentDateFixCommand extends Command
{

    protected $cachedUserIds = [];
    protected $cachedLeadIds = [];
    protected $signature = 'wap-sendings:fix-sent-dates {--chunk=} {--offset=}';
    protected $description = 'Fix WhatsApp Sendings first and last sent dates';


    public function __construct()
    {
        parent::__construct();
    }


    // Multiple docs version
    public function handle()
    {
        $limit = (int) ($this->option('chunk') ?? 500);
        $offset = (int) ($this->option('offset') ?? 0);
        $wapSendings = WhatsAppSending::whereNull('first_sent_message_date')
            ->orWhereNull('last_sent_message_date')
            ->get()
        ;
        foreach ($wapSendings as $wapSending) {
            $msgs = $wapSending->whatsAppSendingMessages;
            $firstMsg = $msgs->whereNotNull('sent_date')->first();
            $lastMsg = $msgs->whereNotNull('sent_date')->last();

            $this->info("\n-----------------------------------");
            $this->info("- WAP Sending ID: {$wapSending->id}");
            $this->info("-----------------------------------\n");

            $wapSending->first_sent_message_date = $firstMsg?->sent_date;
            $wapSending->last_sent_message_date = $lastMsg?->sent_date;

            $this->info("- first_sent_message_date: {$firstMsg?->sent_date}");
            $this->info("- last_sent_message_date: {$lastMsg?->sent_date}");

            $wapSending->saveOrFail();
        }
    }


}
