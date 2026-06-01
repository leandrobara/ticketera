<?php

namespace App\Repositories;

use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Traits\VoidClearCache;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class ClientRepository implements Repository
{

    use VoidClearCache;


    public function listPaginated(array $opts = []): LengthAwarePaginator
    {
        $filters = $opts['filters'] ?? [];
        $limit = $opts['limit'] ?? 9999;
        $pageNumber = $opts['page'] ?? 1;
        $order = $opts['order'] ?? 'name ASC';
        $relationshipsToEagerLoad = $opts['with'] ?? [];

        $queryBuilder = Client::query();

        if ($relationshipsToEagerLoad) {
            $queryBuilder->with($opts['with']);
        }

        $queryBuilder = $this->applyFilters($queryBuilder, $filters);

        $queryBuilder->orderByRaw($order);

        $result = $queryBuilder->paginate($limit, ['*'], 'page', $pageNumber);

        return $result;
    }


    public function findOneBySubdomain(string $subdomain): ?Client
    {
        return Client::where('subdomain', $subdomain)->first();
    }


    public function findClientByLeadsId(int $clientLeadsId): ?Client
    {
        return Client::where('leads_client_id', $clientLeadsId)->first();
    }


    public function findAllEnabled(): Collection
    {
        return Client::where('enabled', true)->get();
    }


    public function findWithEnabledWAPI(): Collection
    {
        return Client::where('enabled', true)
            ->whereHas('clientSettings', function ($query) {
                $query->where('enable_wapi', true);
            })
            ->get()
        ;
    }


    public function findWithEnabledWAPSenderJob(array $opts = []): Collection
    {
        $query = Client::where('enabled', true)
            ->whereHas('clientSettings', function ($query) {
                $query->where('enable_whatsapp_sender_job_sending', true);
            })
        ;
        if ($opts['with'] ?? null) {
            $query->with($opts['with']);
        }
        $query->orderBy('id', 'asc');
        return $query->get();
    }


    public function findWithEnabledWAPIOrWapSender(): Collection
    {
        return Client::where('enabled', true)
            ->whereHas('clientSettings', function ($query) {
                $query->where('enable_wapi', true)->orWhere('enable_whatsapp_sender_extension', true);
            })
            ->get()
        ;
    }


    public function findWithEnabledWAPIOrWapSenderJob(): Collection
    {
        return Client::where('enabled', true)
            ->whereHas('clientSettings', function ($query) {
                $query
                    ->where('enable_wapi', true)
                    ->orWhere('force_whatsapp_meta_api', true)
                    ->orWhere('enable_whatsapp_sender_job_sending', true)
                ;
            })
            ->get()
        ;
    }


    public function findWithEnabledWhatsAppMetaAPI(): Collection
    {
        return Client::where('enabled', true)
            ->whereHas('clientSettings', function ($query) {
                $query->where('enable_whatsapp_meta_api', true);
            })
            ->get()
        ;
    }


    public function findWithEnabledNewLeadWhatsAppMessageAlert(): Collection
    {
        return Client::where('enabled', true)
            ->whereHas('clientSettings', function ($query) {
                $query->where('enable_new_lead_whatsapp_message_alert', true);
            })
            ->get()
        ;
    }
    

    public function findWithEnabledDailyTaskWhatsAppMessageAlert(): Collection
    {
        return Client::where('enabled', true)
            ->whereHas('clientSettings', function ($query) {
                $query->where('enable_daily_task_whatsapp_message_alert', true);
            })
            ->get()
        ;
    }


    public function findWithEnabledTaskHourReminderWhatsAppMessageAlert(): Collection
    {
        return Client::where('enabled', true)
            ->whereHas('clientSettings', function ($query) {
                $query->where('enable_task_hour_reminder_whatsapp_message_alert', true);
            })
            ->get()
        ;
    }


    public function findWithEnabledDailyEmailForEachExpiredTask(): Collection
    {
        return Client::where('enabled', true)
            ->whereHas('clientSettings', function ($query) {
                $query->where('enable_daily_email_for_each_expired_task', true);
            })
            ->get()
        ;
    }


    public function findWithEnabledDailyEmailForAllExpiredTasks(): Collection
    {
        return Client::where('enabled', true)
            ->whereHas('clientSettings', function ($query) {
                $query->where('enable_daily_email_for_all_expired_tasks', true);
            })
            ->get()
        ;
    }


    public function count(): int
    {
        return Client::where('enabled', true)->get()->count();
    }


    public function findOneById(int $id): ?Client
    {
        return Client::find($id);
    }


    public function update(Client $client, array $data): Client
    {
        $client->fill($data);
        $client->saveOrFail();
        return $client->fresh();
    }


    protected function applyFilters(object $queryBuilder, array $filters): object
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
