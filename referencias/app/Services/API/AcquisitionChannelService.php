<?php

namespace App\Services\API;

use App\Models\Client;
use App\Repositories\Repository;
use Illuminate\Support\Collection;
use App\Models\AcquisitionChannel;
use App\Services\Traits\GetClientFromRequest;


class AcquisitionChannelService
{

    use GetClientFromRequest;

    private $acquisitionChannelRepository;


    public function __construct(Repository $acquisitionChannelRepository)
    {
        $this->acquisitionChannelRepository = $acquisitionChannelRepository;
    }


    public function findAllByClient()
    {
        return $this->acquisitionChannelRepository->findAllByClient($this->getClient());
    }


    public function findByClientAndIds(Client $client, array $ids): Collection
    {
        return $this->acquisitionChannelRepository->findByClientAndIds($client, $ids);
    }


    public function create(array $data, ?Client $client = null)
    {
        $client = $client ?? $this->getClient();
        $data['client_id'] = $client->id;
        
        $existentChannel = $this->acquisitionChannelRepository->findOneWithTrashedByClientAndName(
            $client, $data['name']
        );
        if ($existentChannel && $existentChannel->deleted_at) {
            $existentChannel->deleted_at = null;
            $existentChannel->fill($data);
            $existentChannel->save();
            $this->acquisitionChannelRepository->clearCacheForClient($client->id);
            return $existentChannel;
        }
        $newChannel = $this->acquisitionChannelRepository->create($data);
        return $newChannel;
    }


    public function getLeadsCount(AcquisitionChannel $acquisitionChannel)
    {
        return $acquisitionChannel->leadsCount;
    }


    public function update(AcquisitionChannel $acquisitionChannel, array $data)
    {
        return $this->acquisitionChannelRepository->update($acquisitionChannel, $data);
    }


    public function delete(AcquisitionChannel $acquisitionChannel)
    {
        return $this->acquisitionChannelRepository->delete($acquisitionChannel);
    }


    public function findOneByClientAndName(Client $client, string $name): ?AcquisitionChannel
    {
        return $this->acquisitionChannelRepository->findOneByClientAndName($client, $name);
    }


    public function findOneWithTrashedByClientAndName(Client $client, string $name): ?AcquisitionChannel
    {
        return $this->acquisitionChannelRepository->findOneWithTrashedByClientAndName($client, $name);
    }


    public function findOrCreateByClientAndName(Client $client, string $name, array $attrs = []): AcquisitionChannel
    {
        $channel = $this->findOneWithTrashedByClientAndName($client, $name);
        if ($channel && $channel->deleted_at) {
            $channel->deleted_at = null;
            if ($attrs) {
                $channel->fill($attrs);
                $channel->saveOrFail();
                $this->acquisitionChannelRepository->clearCacheForClient($client->id);
                return $channel->fresh();
            }
        }
        if (!$channel) {
            $data = ['client_id' => $client->id, 'name' => ucfirst(strtolower($name))] + $attrs;
            $channel = $this->create($data, $client);
        }
        return $channel;
    }


    public function createNewClientDefaults(Client $client): Collection
    {
        $names = ['Sitio web', 'Landing Page', 'Facebook', 'Referido', 'Llamado telefónico'];
        $channelList = collect([]);
        foreach ($names as $i => $name) {
            $hash = AcquisitionChannel::buildHash($name);
            $attrs = ['client_id' => $client->id, 'name' => $name, 'hash' => $hash, 'order' => $i];
            $channel = new AcquisitionChannel($attrs);
            $channel->saveOrFail();
            $channelList->push($channel->fresh());
        }
        return $channelList;
    }

}
