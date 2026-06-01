<?php

namespace App\Repositories;

use DateTime;
use App\Models\Email;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\EmailNotificationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class EmailNotificationLogRepository
{

    public function listPaginated(Client $client, array $options = []): LengthAwarePaginator
    {
        $relationshipsToEagerLoad = $options['with'] ?? [];
        $queryBuilder = EmailNotificationLog::query();
        $queryBuilder->where('client_id', $client->id);
        if ($relationshipsToEagerLoad) {
            $queryBuilder->with($relationshipsToEagerLoad);
        }
        // if ($options['sort']) {
        //     $queryBuilder->orderBy('email.lead_id', 'desc');
        // }
        $queryBuilder = $this->applyFilters($queryBuilder, $options['filters']);
        $paginated = $queryBuilder->paginate($options['limit'], ['*'], 'page', $options['page']);
        return $paginated;
    }


    public function findLastOpenLogWithReopenedProposalEmailNotification(Email $email): ?EmailNotificationLog
    {
        return EmailNotificationLog::where('email_id', $email->id)
            ->limit(1)
            ->orderBy('id', 'desc')
            ->where('event', 'open')
            ->whereNotNull('reopened_proposal_email_notification_date')
            ->first()
        ;
    }


    public function findLastOpenLogWithReopenedProposalBrowserNotification(Email $email): ?EmailNotificationLog
    {
        return EmailNotificationLog::where('email_id', $email->id)
            ->limit(1)
            ->orderBy('id', 'desc')
            ->where('event', 'open')
            ->whereNotNull('reopened_proposal_browser_notification_date')
            ->first()
        ;
    }


    public function findLastOpenLog(Email $email): ?EmailNotificationLog
    {
        return EmailNotificationLog::where('email_id', $email->id)
            ->limit(1)
            ->orderBy('id', 'desc')
            ->where('event', 'open')
            ->first()
        ;
    }


    public function create(array $data): EmailNotificationLog
    {
        $emailNotificationLog = new EmailNotificationLog($data);
        $emailNotificationLog->saveOrFail();
        return $emailNotificationLog->fresh();
    }


    public function markLogWithReopenedProposalEmailNotified(
        EmailNotificationLog $emailNotificationLog
    ): EmailNotificationLog {
        $emailNotificationLog->reopened_proposal_email_notification_date = new DateTime('now');
        $emailNotificationLog->saveOrFail();
        return $emailNotificationLog->fresh();
    }


    public function markLogWithReopenedProposalBrowserNotified(
        EmailNotificationLog $emailNotificationLog
    ): EmailNotificationLog {
        $emailNotificationLog->reopened_proposal_browser_notification_date = new DateTime('now');
        $emailNotificationLog->saveOrFail();
        return $emailNotificationLog->fresh();
    }


    protected function applyFilters(Builder $queryBuilder, array $filters): Builder
    {
        foreach ($filters as $key => $value) {
            if (isset($filters[$key])) {
                if (is_array($value)) {
                    $queryBuilder->whereIn($key, $value);
                } elseif ($filters[$key] instanceof SQLFilterCriteria) {
                    $queryBuilder = $filters[$key]->filterSQLQuery($queryBuilder);
                } else {
                    $queryBuilder->where($key, $value);
                }
            }
        }
        return $queryBuilder;
    }

}
