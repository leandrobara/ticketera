<?php

namespace App\Jobs\LeadEvents;

use Throwable;
use Exception;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use App\Models\LeadContactEmail;
use App\Helpers\EmailValidatorHelper;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\LeadEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\Dispatchers\EmailValidationEventsDispatcherService;


class ValidateLeadEmailsJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $leadId;
    

    public function __construct(int $leadId)
    {
        $this->leadId = $leadId;
    }


    public function handle()
    {
        $lead = Lead::find($this->leadId);
        if (!$lead) {
            return true;
        }
        $dispatcher = resolve(EmailValidationEventsDispatcherService::class);
        $leadContactEmails = $lead->leadContactEmails();
        foreach ($leadContactEmails as $leadContactEmail) {
            $dispatcher->dispatchValidateLeadContactEmailJob($leadContactEmail);
        }
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }

}
