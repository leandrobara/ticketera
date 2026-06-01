<?php

namespace App\Repositories;

use DateTime;
use Exception;
use App\Models\User;
use App\Models\Client;
use App\Models\WhatsAppSending;
use App\Repositories\Repository;
use App\Models\AcquisitionChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Traits\VoidClearCache;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Criteria\Sort\SortCriteria;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;
use App\Repositories\Criteria\Sort\WhatsAppSending\SortBySent;


class WhatsAppSendingRepository implements Repository
{

    use VoidClearCache;


    public function findWAPIScheduledEnabledToSendBetweenSendDatesByClient(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd,
        array $opts = []
    ): Collection {
        $queryBuilder = WhatsAppSending::query()
            ->where('type', WhatsAppSending::WAPI_TYPE)
            ->where('client_id', $client->id)
            ->whereNull('finished_date')
            ->whereNull('cancelled_date')
            ->whereNull('paused_date')
            ->whereNull('failed_date')
            ->whereNotNull('send_date')
            ->whereNull('first_sent_message_date')
            ->where('send_date', '>', $dateStart)
            ->where('send_date', '<', $dateEnd)
            ->orderBy('send_date', 'asc')
        ;
        $queryBuilder->whereHas('whatsAppSendingMessages', function ($q) {
            $q->whereNull('dispatched_date')->whereNull('error_message');
        });

        if ($opts['limit'] ?? null) {
            $queryBuilder->limit($opts['limit']);
        }
        $whatsAppSendings = $queryBuilder->get();
        return $whatsAppSendings;
    }


    public function findCurrentSendingByUserAndType(User $user, string $type): ?WhatsAppSending
    {
        $whatsAppSending = WhatsAppSending::query()
            ->where('type', $type)
            ->where('user_id', $user->id)
            ->whereNull('finished_date')
            ->whereNull('cancelled_date')
            ->orderBy('id', 'desc')
            ->first()
        ;
        return $whatsAppSending;
    }


    public function findLastByUserAndType(User $user, string $type): ?WhatsAppSending
    {
        $whatsAppSending = WhatsAppSending::query()
            ->where('type', $type)
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->orWhereNotNull(['finished_date', 'cancelled_date']);
            })
            ->orderBy('id', 'desc')
            ->first()
        ;
        return $whatsAppSending;
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        $whatsAppSendings = WhatsAppSending::query()
            ->where('client_id', $client->id)
            ->whereIn('id', $ids)
            ->get()
        ;
        return $whatsAppSendings;
    }


    public function findProposalsBetweenSentDatesByClient(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd
    ): Collection {
        DB::enableQueryLog();
        $proposals = WhatsAppSending::whereNotNull('finished_date')
            ->where('finished_date', '>=', $dateStart)
            ->where('finished_date', '<=', $dateEnd)
            ->where('client_id', $client->id)
            ->where('is_proposal', true)
            ->where('is_automation', false)
            ->where(function ($q) {
                $q->where('type', WhatsAppSending::WAPI_TYPE)
                    ->orWhere('type', WhatsAppSending::WAP_SENDER_JOB_TYPE)
                    ->orWhere('type', WhatsAppSending::WHATSAPP_META_API_TYPE)
                ;
            })
            ->get()
        ;
        return $proposals;
    }


    public function listPaginated(Client $client, array $opts = []): LengthAwarePaginator
    {
        $relationshipsToEagerLoad = $opts['with'] ?? [];
        $queryBuilder = WhatsAppSending::query();
        $queryBuilder->where('client_id', $client->id);
        
        if ($relationshipsToEagerLoad) {
            $queryBuilder->with($relationshipsToEagerLoad);
        }
        $queryBuilder = $this->applyFilters($queryBuilder, $opts['filters']);

        $sort = $opts['sort'] ?? 'id desc';
        if ($sort) {
            if (is_a($sort, SortCriteria::class)) {
                $queryBuilder = $sort->applySort($queryBuilder);
            } else {
                $queryBuilder->orderByRaw($sort);
            }
        }

        $paginated = $queryBuilder->paginate($opts['limit'], ['*'], 'page', $opts['page']);
        return $paginated;
    }


    public function create(array $data): WhatsAppSending
    {
        $wapSending = new WhatsAppSending($data);
        $wapSending->saveOrFail();
        return $wapSending->fresh();
    }


    public function update(
        WhatsAppSending $whatsAppSending,
        array $newAttrs,
        array $opts = []
    ): WhatsAppSending {
        $isRefreshEnabled = $opts['isRefreshEnabled'] ?? true;

        $updated = clone $whatsAppSending;
        $updated->fill($newAttrs);
        $updated->saveOrFail();
        if ($isRefreshEnabled) {
            $updated = $updated->fresh();
        }
        return $updated;
    }


    public function cancel(WhatsAppSending $wapSending): WhatsAppSending
    {
        $wapSending->paused_date = null;
        $wapSending->cancelled_date = new DateTime('now');
        $wapSending->saveOrFail();
        return $wapSending->fresh();
    }


    public function pause(WhatsAppSending $wapSending, ?string $pauseReason = null): WhatsAppSending
    {
        $wapSending->paused_date = new DateTime('now');
        $wapSending->pause_reason = $pauseReason;
        $wapSending->saveOrFail();
        return $wapSending->fresh();
    }
    

    public function resume(WhatsAppSending $wapSending): WhatsAppSending
    {
        $wapSending->paused_date = null;
        $wapSending->saveOrFail();
        return $wapSending->fresh();
    }


    public function finish(WhatsAppSending $wapSending): WhatsAppSending
    {
        $wapSending->finished_date = new DateTime('now');
        $wapSending->saveOrFail();
        return $wapSending->fresh();
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
