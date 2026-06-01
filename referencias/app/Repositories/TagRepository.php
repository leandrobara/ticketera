<?php

namespace App\Repositories;

use Exception;
use App\Models\Tag;
use App\Models\Lead;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Traits\VoidClearCache;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class TagRepository implements Repository
{

    use VoidClearCache;


    public function findAllByClient(Client $client): Collection
    {
        return Tag::where('client_id', $client->id)->get();
    }


    public function findOneByClientAndName(Client $client, string $name): ?Tag
    {
        $hash = Tag::buildHash($name);
        return Tag::where('hash', $hash)->where('client_id', $client->id)->first();
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->findByClientIdAndIds($client->id, $ids);
    }


    public function findByClientIdAndIds(int $clientId, array $ids): Collection
    {
        return Tag::whereIn('id', $ids)->where('client_id', $clientId)->get();
    }


    public function findWithTrashedByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->findWithTrashedByClientIdAndIds($client->id, $ids);
    }


    public function findWithTrashedByClientIdAndIds(int $clientId, array $ids): Collection
    {
        return Tag::withTrashed()->whereIn('id', $ids)->where('client_id', $clientId)->get();
    }


    public function findOrFail(int $id): Tag
    {
        return Tag::findOrFail($id);
    }


    public function findOneWithTrashedByClientAndName(Client $client, string $name): ?Tag
    {
        $hash = Tag::buildHash($name);
        return Tag::withTrashed()->where('hash', $hash)->where('client_id', $client->id)->first();
    }


    public function create(array $data): Tag
    {
        $data['hash'] = Tag::buildHash($data['name']);
        $tag = new Tag($data);
        $tag->saveOrFail();
        return $tag->fresh();
    }


    public function update(Tag $tag, array $data): Tag
    {
        if (isset($data['name'])) {
            $data['hash'] = Tag::buildHash($data['name']);
        }
        $tag->fill($data);
        $tag->saveOrFail();
        return $tag->fresh();
    }


    public function delete(Tag $tag): Tag
    {
        $tag->leads()->detach();
        $tag->delete();
        return $tag->fresh();
    }


    public function list(Client $client, array $options = []): Collection
    {
        $queryBuilder = $this->buildFindQueryBuilder($options, $client);
        if ($options['with'] ?? []) {
            $queryBuilder->with($options['with']);
        }
        if ($options['withCount'] ?? []) {
            $queryBuilder->withCount($options['withCount']);
        }
        $tags = $queryBuilder->get();
        return $tags;
    }


    protected function buildFindQueryBuilder(array $options = [], ?Client $client = null): Builder
    {
        $order = $options['sort'] ?? [];
        $filters = $options['filters'] ?? [];

        $queryBuilder = Tag::query();
        if ($client) {
            $queryBuilder->where('client_id', $client->id);
        }

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
        if ($order) {
            $queryBuilder->orderByRaw($order);
        }
        return $queryBuilder;
    }

}
