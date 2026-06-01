<?php

namespace App\Services\API\Dispatchers;

use App\Models\Lead;
use App\Models\LeadContactEmail;
use Illuminate\Support\Collection;
use App\Services\Traits\CustomDispatch;
use Illuminate\Foundation\Bus\PendingDispatch;
use App\Jobs\LeadEvents\ValidateLeadEmailsJob;
use App\Jobs\LeadEvents\ValidateLeadContactEmailJob;


class EmailValidationEventsDispatcherService
{

    use CustomDispatch;

    private $eventQueueName;
    private $queueConnection;


    public function __construct(string $eventQueueName, string $queueConnection)
    {
        $this->eventQueueName = $eventQueueName;
        $this->queueConnection = $queueConnection;
    }


    public function dispatchValidateLeadEmailsJob(Lead $lead, ?int $delaySecs = null)
    {
        $this->doCustomDispatch(ValidateLeadEmailsJob::class, [$lead->id], $delaySecs, $lead->client_id);
    }


    public function dispatchValidateLeadContactEmailJob(LeadContactEmail $leadContactEmail, ?int $delaySecs = null)
    {
        $this->doCustomDispatch(
            ValidateLeadContactEmailJob::class, [$leadContactEmail->id], $delaySecs, $leadContactEmail->client_id
        );
    }

}
