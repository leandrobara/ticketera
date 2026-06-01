<?php

namespace App\Jobs\WhatsAppEvents;

use Throwable;
use App\Models\User;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use App\Models\WhatsAppSending;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\API\WhatsAppSendingService;
use App\Jobs\WhatsAppEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class FinishWAPISendingIfEndedJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;


    public function __construct(
        public readonly int $wapSendingId,
    ) {
    }


    public function handle()
    {
        $wapSending = WhatsAppSending::findOrFail($this->wapSendingId);
        resolve(WhatsAppSendingService::class)->finishIfApplicable($wapSending);
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
        $this->getErrorLog()->error(PHP_EOL . PHP_EOL);
    }

}
