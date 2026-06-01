<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppSending;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Services\API\WhatsAppSendingMessageTextService;


class WhatsAppSendingsSetMessageTextIdCommand extends Command
{

    protected $cachedUserIds = [];
    protected $cachedLeadIds = [];
    protected $signature = 'wap-sendings:set-message-text-id {--chunk=} {--offset=}';
    protected $description = 'Fix WhatsApp Sendings set whatsapp_sending_message_text_id from message field';


    public function __construct()
    {
        parent::__construct();
    }


    // Multiple docs version
    public function handle()
    {
        $limit = (int) ($this->option('chunk') ?? 500);
        $offset = (int) ($this->option('offset') ?? 0);
        $wapSendings = WhatsAppSending::whereNull('whatsapp_sending_message_text_id')->get();
        $wapMsgTxtService = resolve(WhatsAppSendingMessageTextService::class);

        foreach ($wapSendings as $wapSending) {
            $messageText = $wapSending->message;
            $wapMsgTxtModel = $wapMsgTxtService->findOrCreate($messageText);

            $wapSending->whatsapp_sending_message_text_id = $wapMsgTxtModel->id;
            $wapSending->saveOrFail();

            $this->info("\n-----------------------------------");
            $this->info("- WAP Sending ID: {$wapSending->id}");
            $this->info("- WAP Msg Text ID: {$wapMsgTxtModel->id}");
            $this->info("-----------------------------------\n");
        }
    }

}
