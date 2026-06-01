<?php

namespace App\Repositories\Cache;

use DateTime;
use App\Models\Lead;
use App\Models\Client;
use App\Models\LeadSale;
use App\Helpers\RedisHelper;
use App\Repositories\Repository;
use Illuminate\Support\Collection;


class LeadSaleRepositoryCache extends RepositoryBaseCache implements Repository
{

    public function findAllByClient(Client $client, array $opts = []): Collection
    {
        // Si viene con opciones NO cacheo, ya que viene con closures y demás cosas no serializables.
        if ($opts) {
            return $this->repository->findAllByClient($client, $opts);
        }
        $opts = ['storeEmpty' => true];
        $key = $this->getMethodRedisKey(md5(serialize($opts)));
        $leadSales = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args(), $opts);
        return $leadSales;
    }


    public function listByClientAndLeadIds(Client $client, array $leadIds, array $opts = []): Collection
    {
        // Si viene con opciones NO cacheo, ya que viene con closures y demás cosas no serializables.
        if ($opts) {
            return $this->repository->listByClientAndLeadIds($client, $leadIds, $opts);
        }
        $keyArr = [$leadIds, $opts];
        $opts = ['storeEmpty' => true];
        $key = $this->getMethodRedisKey(md5(serialize($keyArr)));
        $leadSales = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args(), $opts);
        return $leadSales;
    }


    public function findByClientAndDates(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd,
        array $opts = []
    ): Collection {
        // Si viene con opciones NO cacheo, ya que viene con closures y demás cosas no serializables.
        if ($opts) {
            return $this->repository->findByClientAndDates($client, $dateStart, $dateEnd, $opts);
        }
        $opts = ['storeEmpty' => true];
        $keyArr = [$dateStart->format('Y-m-d'), $dateEnd->format('Y-m-d'), $opts];
        $key = $this->getMethodRedisKey(md5(serialize($keyArr)));
        $leadSales = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args(), $opts);
        return $leadSales;
    }


    public function findByClientAndDatesGroupingByLeadDisctinct(
        Client $client,
        DateTime $dateStart,
        DateTime $dateEnd
    ): Collection {
        $opts = ['storeEmpty' => true];
        $keyArr = [$dateStart->format('Y-m-d'), $dateEnd->format('Y-m-d')];
        $key = $this->getMethodRedisKey(md5(serialize($keyArr)));
        $leadSales = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args(), $opts);
        return $leadSales;
    }


    public function findLastSaleByLead(Lead $lead): LeadSale
    {
        $key = $this->getMethodRedisKey($lead->id);
        $leadSale = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args());
        return $leadSale;
    }


    public function findByClientAndLeads(Client $client, Collection $leads, array $opts = []): Collection
    {
        // Si viene con opciones NO cacheo, ya que viene con closures y demás cosas no serializables.
        if ($opts) {
            return $this->repository->findByClientAndLeads($client, $leads, $opts);
        }
        $opts = ['storeEmpty' => true];
        $keyArr = [$opts, $leads->pluck('id')->toArray()];
        $key = $this->getMethodRedisKey(md5(serialize($keyArr)));
        $leadSales = $this->findOrStoreFromCache($client->id, $key, __FUNCTION__, func_get_args(), $opts);
        return $leadSales;
    }

}
