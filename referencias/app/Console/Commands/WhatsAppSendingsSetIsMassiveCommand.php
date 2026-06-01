<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppSending;
use Illuminate\Console\Command;
use App\Models\WhatsAppSendingMessage;
use Illuminate\Support\Facades\Artisan;
use App\Services\API\WhatsAppSendingMessageTextService;


class WhatsAppSendingsSetIsMassiveCommand extends Command
{

    protected $cachedUserIds = [];
    protected $cachedLeadIds = [];
    protected $description = 'Fix WhatsApp Sendings set is_massive';
    protected $signature = 'wap-sendings:set-is-massive {--chunk=}';


    public function __construct()
    {
        parent::__construct();
    }


    // Multiple docs version
    public function handle()
    {
        $chunk = (int) ($this->option('chunk') ?? 300);
        
        $queryBuilder = WhatsAppSending::query()->where('is_massive', false);
        $queryBuilder->chunk($chunk, function ($wapSendings) {
            foreach ($wapSendings as $wapSending) {
                $wapSendingMsgs = $wapSending->whatsAppSendingMessages;
                $isMassive = $wapSendingMsgs->pluck('lead_id')->unique()->values()->count() > 1;

                $wapSending->is_massive = $isMassive;
                $wapSending->saveOrFail();

                $ids = $wapSendingMsgs->pluck('id');
                $updated = WhatsAppSendingMessage::whereIn('id', $ids)->update(['is_massive' => $isMassive]);

                $this->info("\n-----------------------------------");
                $this->info("- WAP Sending ID: {$wapSending->id}");
                $this->info("- IS_MASSIVE: " . ($isMassive ? '1' : '0'));
                $this->info("-----------------------------------\n");
            }
        });
    }

}
