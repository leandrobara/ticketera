<?php

namespace App\Services\API;

use App\Models\Client;
use App\Models\Landing;
use App\Helpers\UrlHelper;
use App\Repositories\Repository;
use App\Services\Traits\GetClientFromRequest;


class LandingService
{

    use GetClientFromRequest;

    private $landingRepository;


    public function __construct(Repository $landingRepository)
    {
        $this->landingRepository = $landingRepository;
    }


    public function findAllByClient(?Client $client = null)
    {
        $client = $client ?? $this->getClient();
        return $this->landingRepository->findAllByClient($client);
    }


    public function findOrCreateByClientAndUrl(Client $client, string $url, array $attrs = []): Landing
    {
        $url = UrlHelper::normalize($url);
        $landing = $this->landingRepository->findOneByClientAndUrl($client, $url);
        if (!$landing) {
            $landing = $this->create(['client_id' => $client->id, 'url' => $url] + $attrs);
        }
        return $landing;
    }


    public function create($data)
    {
        $data['url'] = UrlHelper::normalize($data['url']);
        if (!($data['client_id'] ?? null)) {
            $data['client_id'] = $this->getClient()->id;
        }
        return $this->landingRepository->create($data);
    }


    public function update(Landing $landing, $data)
    {
        if ($data['url']) {
            $data['url'] = UrlHelper::normalize($data['url']);
        }
        return $this->landingRepository->update($landing, $data);
    }


    public function delete(Landing $landing)
    {
        return $this->landingRepository->delete($landing);
    }

}
