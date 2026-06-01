<?php

namespace App\Jobs\EmailEvents;

use Throwable;
use App\Models\Lead;
use App\Models\User;
use App\Models\Email;
use Illuminate\Bus\Queueable;
use App\Services\API\UserService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\API\ProposalInfoService;
use App\Jobs\EmailEvents\Traits\InjectLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Overrides\Dispatchers\CustomDispatchable;
use App\Services\API\Dispatchers\TimelineEventsDispatcherService;


class MassiveEmailSentOrScheduledJob implements ShouldQueue
{

    use CustomDispatchable, InteractsWithQueue, Queueable, SerializesModels, InjectLog;

    public $tries = 2;
    public $backoff = 60;
    
    public $type;
    public $userId;
    public $emailsExternalIds;
    

    public function __construct(int $userId, array $emailsExternalIds, string $type)
    {
        $this->type = $type;
        $this->userId = $userId;
        $this->emailsExternalIds = $emailsExternalIds;
    }


    public function handle()
    {
        if (!$this->emailsExternalIds) {
            return null;
        }

        $user = resolve(UserService::class)->findOrFail($this->userId);
        $emails = Email::where('is_proposal', true)->whereIn('external_id', $this->emailsExternalIds)->get();
        $emailsGroupedByLeadId = $emails->groupBy('lead_id');
        foreach ($emailsGroupedByLeadId as $leadId => $leadGroupedEmails) {
            $emailIds = $leadGroupedEmails->pluck('id')->toArray();
            $proposalInfoData = [
                'amount' => 0,
                'status' => 'opened',
                'description' => null,
                'user_id' => $user->id,
                'email_ids' => $emailIds,
                'client_id' => $user->client->id,
                'sent_date' => $leadGroupedEmails->first()->send_date,
            ];
            $lead = Lead::findOrFail($leadId);
            resolve(ProposalInfoService::class)->create($lead, $proposalInfoData);
        }

        if ($this->type == 'schedule') {
            $delaySeconds = 5;
            $timelineDispatcherService = resolve(TimelineEventsDispatcherService::class);
            $emails = Email::whereIn('external_id', $this->emailsExternalIds)->get(['id', 'lead_id', 'user_id']);
            foreach ($emails as $email) {
                $email->load('lead');
                $email->setRelation('user', $user);
                $timelineDispatcherService->leadEmailScheduled($email, $delaySeconds);
            }
        }
    }


    public function failed(Throwable $e)
    {
        $this->getErrorLog()->error($e);
    }


    protected function logStartMessage()
    {
        $this->getInfoLog()->info('STARTING ' . self::class . ' ...');
        $this->getInfoLog()->info('- External email IDs: ' . implode(', ', $this->emailsExternalIds));
    }


    protected function logEndMessage()
    {
        $this->getInfoLog()->info('ENDED ' . self::class . PHP_EOL);
    }

}
