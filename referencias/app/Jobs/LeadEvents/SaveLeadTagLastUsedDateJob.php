<?php

namespace App\Jobs\LeadEvents;

use DateTime;
use Exception;
use Throwable;
use Illuminate\Bus\Queueable;
use App\Services\API\TagService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Jobs\TimelineEvents\Traits\InjectLog;
use App\Overrides\Dispatchers\CustomDispatchable;


class SaveLeadTagLastUsedDateJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    

    public function __construct(
        public int $tagId,
        public DateTime $lastUsedDate
    ) {
    }


    public function handle()
    {
        $tagService = resolve(TagService::class);
        $tag = $tagService->findOrFail($this->tagId);
        if ($tag) {
            $tagService->update($tag, ['last_used_date' => $this->lastUsedDate]);
        }
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
        $this->getErrorLog()->error(PHP_EOL . PHP_EOL);
    }

}
