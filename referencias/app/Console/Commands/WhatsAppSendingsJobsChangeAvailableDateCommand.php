<?php

namespace App\Console\Commands;

use DateTime;
use App\Models\WhatsAppSending;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\WhatsAppSendingMessage;
use Illuminate\Support\Facades\Artisan;
use App\Services\API\WhatsAppSendingService;


class WhatsAppSendingsJobsChangeAvailableDateCommand extends Command
{

    protected $cachedUserIds = [];
    protected $cachedLeadIds = [];
    protected $signature = 'wap-sendings:change-jobs-available-date {--chunk=}';
    protected $description = 'Fix WhatsApp Sendings jobs: changes all available dates so they do not run all at once';


    public function __construct()
    {
        parent::__construct();
    }


    // Multiple docs version
    public function handle()
    {
        $queueName = config('queue.whatsapp_events');
        $nowTs = (new DateTime())->getTimestamp();
        $queryBuilder = DB::connection('mysql_worker')
            ->table('jobs')
            ->where('queue', $queueName)
            ->where('available_at', '<', $nowTs)
            ->orderBy('available_at')
        ;
        $jobs = $queryBuilder->get();
        if ($jobs->isEmpty()) {
            $this->info('No queued jobs!');
            die();
        }

        $overheadSecs = 240; // Para que arranquen en 4 minutos.
        $minAvailableTs = $jobs->first()->available_at;
        $diffSecs = ($nowTs - $minAvailableTs) + $overheadSecs;

        foreach ($jobs as $job) {
            $payloadArr = json_decode($job->payload, true);
            $commandObj = unserialize($payloadArr['data']['command']);
            $whatsAppSendingMessageId = $commandObj->whatsAppSendingMessageId;

            $newAvailableAt = $job->available_at + $diffSecs;

            $updated = DB::connection('mysql_worker')
                ->table('jobs')
                ->where('id', $job->id)
                ->where('queue', $queueName)
                ->update(['available_at' => $newAvailableAt])
            ;
            if ($updated) {
                $this->info("- Job ID: {$job->id} updated (WapSendingMsgId: {$whatsAppSendingMessageId})");
                $this->info("Old available_at: {$job->available_at}. New available_at: {$newAvailableAt} \n");
            } else {
                $this->error("- Job ID: {$job->id} NOT updated");
            }
        }
        
    }

}
