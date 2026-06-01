<?php

namespace App\Services\API;

use App\Models\Tag;
use App\Models\Client;
use Illuminate\Support\Collection;
use App\Repositories\Repository;
use App\Repositories\TagRepository;
use App\Services\Traits\GetClientFromRequest;
use App\Repositories\Criteria\Filter\Tags\MultipleTagCategoryCriteria;


class TagService
{
    
    use GetClientFromRequest;

    private $tagRepository;


    public function __construct(Repository $tagRepository)
    {
        $this->tagRepository = $tagRepository;
    }


    public function findAll()
    {
        return $this->tagRepository->findAllByClient($this->getClient());
    }


    public function getOrCreateOpenedProposalTag(?Client $client = null)
    {
        $tagName = 'Abrio Presupuesto';
        $client = $client ?? $this->getClient();
        $tag = $this->getOrCreate($client, $tagName);
        return $tag;
    }


    public function getOrCreateSentProposalTag(?Client $client = null)
    {
        $tagName = 'Presupuesto enviado';
        $client = $client ?? $this->getClient();
        $tag = $this->getOrCreate($client, $tagName);
        return $tag;
    }


    public function getOrCreateInvalidEmailTag(?Client $client = null)
    {
        $tagName = 'Email inválido';
        $client = $client ?? $this->getClient();
        $tag = $this->getOrCreate($client, $tagName);
        return $tag;
    }


    public function getOrCreateUnsubscribedEmailTag(?Client $client = null)
    {
        $tagName = 'Desuscripto';
        $client = $client ?? $this->getClient();
        $tag = $this->getOrCreate($client, $tagName);
        return $tag;
    }


    public function findOneByClientAndName(Client $client, string $tagName): ?Tag
    {
        return $this->tagRepository->findOneByClientAndName($client, $tagName);
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->findByClientIdAndIds($client->id, $ids);
    }


    public function findByClientIdAndIds(int $clientId, array $ids): Collection
    {
        return $this->tagRepository->findByClientIdAndIds($clientId, $ids);
    }


    public function findWithTrashedByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->findWithTrashedByClientIdAndIds($client->id, $ids);
    }


    public function findWithTrashedByClientIdAndIds(int $clientId, array $ids): Collection
    {
        return $this->tagRepository->findWithTrashedByClientIdAndIds($clientId, $ids);
    }


    public function findOrFail(int $id): Tag
    {
        return $this->tagRepository->findOrFail($id);
    }


    public function getOrCreate(Client $client, $tagName): Tag
    {
        $tag = $this->findOneByClientAndName($client, $tagName);
        if (!$tag) {
            $tag = $this->create(['name' => $tagName], $client);
        }
        return $tag;
    }


    public function getLeadsCount(Tag $tag): int
    {
        return $tag->leadsCount;
    }


    public function create($data, ?Client $client = null): Tag
    {
        $client = $client ?? $this->getClient();
        $tag = $this->tagRepository->findOneWithTrashedByClientAndName($client, $data['name']);
        if ($tag && $tag->deleted_at) {
            $tag->deleted_at = null;
            $tag->fill($data);
            $tag->save();
            $this->tagRepository->clearCacheForClient($client->id);
            return $tag;
        }
        $data['client_id'] = $client->id;
        $newTag = $this->tagRepository->create($data);
        return $newTag;
    }


    public function update(Tag $tag, array $data)
    {
        if (isset($data['name'])) {
            $client = $tag->client;
            $deletedTag = $this->tagRepository->findOneWithTrashedByClientAndName($client, $data['name']);
            if ($deletedTag && $deletedTag->deleted_at) {
                $time = time();
                $deletedTagData = ['name' => "{$deletedTag->name}_deleted_{$time}"];
                $this->tagRepository->update($deletedTag, $deletedTagData);
            }
        }

        return $this->tagRepository->update($tag, $data);
    }


    public function delete(Tag $tag)
    {
        return $this->tagRepository->delete($tag);
    }


    public function list(array $options): Collection
    {
        $client = $options['filters']['client'] ?? $this->getClient();
        unset($options['filters']['client']);

        $opts = [
            'with' => $options['with'] ?? [],
            'withCount' => $options['withCount'] ?? [],
            'filters' => $this->getFilterCriteriasByName($options['filters'] ?? []),
        ];
        return $this->tagRepository->list($client, $opts);
    }


    protected function getFilterCriteriasByName(array $filters): array
    {
        $criterias = [
            'tag_category_id' => MultipleTagCategoryCriteria::class,
        ];

        $nfilters = [];
        foreach ($filters as $key => $value) {
            if (in_array($key, array_keys($criterias)) && $value !== null) {
                $nfilters[$key] = new $criterias[$key]($value);
            } else {
                $nfilters[$key] = $value;
            }
        }
        return $nfilters;
    }

}
